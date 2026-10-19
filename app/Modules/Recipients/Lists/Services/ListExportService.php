<?php

namespace App\Modules\Recipients\Lists\Services;

use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Identity\Models\User;
use App\Modules\Recipients\Lists\Jobs\ExportListJob;
use App\Modules\Recipients\Lists\Support\ListOperationStore;
use App\Modules\Recipients\Models\RecipientList;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\CSV\Writer;

/** docs/45 §45.5.6 — list export: CSV (openspout) on local disk `exports/`, signed temporary link. */
class ListExportService
{
    public const LINK_MINUTES = 60;

    public function __construct(
        private readonly ListOperationStore $operations,
        private readonly AuditLogger $audit,
    ) {}

    /** @return array<string, mixed> */
    public function queue(RecipientList $list, User $actor): array
    {
        $op = $this->operations->start('contacts_export', $actor, (int) $list->contact_count);
        ExportListJob::dispatch($list->id, $op['id']);

        return $this->operations->present($op);
    }

    public function build(RecipientList $list, string $operationId): void
    {
        $this->operations->update($operationId, ['status' => 'running']);

        $relative = 'exports/list-'.$list->uuid.'-'.$operationId.'.csv';
        $disk = Storage::disk('local');
        $disk->makeDirectory('exports');

        $writer = new Writer();
        $writer->openToFile($disk->path($relative));
        $writer->addRow(Row::fromValues(['email', 'first_name', 'last_name', 'company_name', 'status', 'added_at']));

        $rows = 0;
        DB::table('recipient_list_recipient as p')
            ->join('recipients as r', 'r.id', '=', 'p.recipient_id')
            ->where('p.recipient_list_id', $list->id)
            ->whereNull('r.deleted_at')
            ->orderBy('r.id')
            ->select(['r.id', 'r.email', 'r.first_name', 'r.last_name', 'r.company_name', 'r.status', 'p.added_at'])
            ->chunkById(1000, function ($chunk) use ($writer, &$rows): void {
                foreach ($chunk as $r) {
                    $writer->addRow(Row::fromValues([
                        $r->email, $r->first_name, $r->last_name, $r->company_name, $r->status, $r->added_at,
                    ]));
                    $rows++;
                }
            }, 'r.id', 'id');
        $writer->close();

        $expires = now()->addMinutes(self::LINK_MINUTES);
        $url = URL::temporarySignedRoute('lists.exports.download', $expires, ['operation' => $operationId], absolute: false);
        $this->operations->update($operationId, ['total' => $rows]);
        $this->operations->complete($operationId, [
            'download_url' => $url,
            'expires_at' => $expires->toIso8601String(),
            'row_count' => $rows,
            'path' => $relative,
        ]);

        $actorId = $this->operations->find($operationId)['user_id'] ?? null;
        $this->audit->record('contacts.exported', $list, $actorId ? User::query()->find($actorId) : null, null, [
            'list' => $list->name, 'row_count' => $rows, 'format' => 'csv',
        ]);
    }

    /** Hides the internal storage path from API consumers. */
    public function present(array $op): array
    {
        $op = $this->operations->present($op);
        if (isset($op['result']['path'])) {
            unset($op['result']['path']);
        }

        return $op;
    }
}
