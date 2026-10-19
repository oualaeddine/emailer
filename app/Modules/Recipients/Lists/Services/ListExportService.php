<?php

namespace App\Modules\Recipients\Lists\Services;

use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Identity\Models\User;
use App\Modules\Recipients\Lists\Jobs\ExportListJob;
use App\Modules\Recipients\Views\Contracts\AsyncOperations;
use App\Modules\Recipients\Views\Models\AsyncOperation;
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
        private readonly AsyncOperations $operations,
        private readonly AuditLogger $audit,
    ) {}

    public function queue(RecipientList $list, User $actor): AsyncOperation
    {
        $op = $this->operations->start('contacts_export', $actor, ['list_id' => $list->id], (int) $list->contact_count);
        ExportListJob::dispatch($list->id, $op->id);

        return $op->fresh();
    }

    public function build(RecipientList $list, AsyncOperation $op): void
    {
        $this->operations->progress($op, 0);

        $relative = 'exports/list-'.$list->uuid.'-'.$op->uuid.'.csv';
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
                    $writer->addRow(Row::fromValues(array_map(self::safeCell(...), [
                        $r->email, $r->first_name, $r->last_name, $r->company_name, $r->status, $r->added_at,
                    ])));
                    $rows++;
                }
            }, 'r.id', 'id');
        $writer->close();

        $expires = now()->addMinutes(self::LINK_MINUTES);
        $url = URL::temporarySignedRoute('lists.exports.download', $expires, ['operation' => $op->uuid], absolute: false);
        $op->forceFill(['processed' => $rows, 'total' => $rows, 'payload' => array_merge($op->payload ?? [], ['path' => $relative])]);
        $this->operations->complete($op, [
            'download_url' => $url,
            'expires_at' => $expires->toIso8601String(),
            'row_count' => $rows,
        ]);

        $this->audit->record('contacts.exported', $list, $op->user_id ? User::query()->find($op->user_id) : null, null, [
            'list' => $list->name, 'row_count' => $rows, 'format' => 'csv',
        ]);
    }

    /** CSV/formula-injection guard: neutralise cells a spreadsheet would evaluate. */
    public static function safeCell(mixed $value): mixed
    {
        if (is_string($value) && $value !== '' && preg_match('/^[=+\-@\t\r]/', $value) === 1) {
            return "'".$value;
        }

        return $value;
    }
}
