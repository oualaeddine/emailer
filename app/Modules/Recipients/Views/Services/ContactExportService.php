<?php

namespace App\Modules\Recipients\Views\Services;

use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Identity\Models\User;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Recipients\Views\Jobs\ExportContactsJob;
use App\Modules\Recipients\Views\Models\AsyncOperation;
use App\Modules\Recipients\Views\Support\ContactColumns;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\CSV\Options as CsvOptions;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

/**
 * docs/45 §45.5.4 — `POST /recipients/export`: CSV (UTF-8 BOM, `;`) or XLSX
 * written to the local `exports/` directory, served through a signed URL.
 */
class ContactExportService
{
    public const DISK = 'local';

    public const DIRECTORY = 'exports';

    public function __construct(
        private readonly ContactQueryService $queries,
        private readonly AsyncOperationService $operations,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $selection
     * @param  list<mixed>  $columns
     */
    public function start(User $user, array $selection, array $columns, string $format): AsyncOperation
    {
        $keys = $columns === [] ? array_column(ContactColumns::defaults(), 'key') : ContactColumns::keys($columns);
        $count = $this->queries->selectionQuery($selection, $user)->count();

        $operation = $this->operations->start('contacts_export', $user, [
            'selection' => $selection, 'columns' => $keys, 'format' => $format,
        ], $count);

        ExportContactsJob::dispatch($operation->id);

        return $operation->fresh();
    }

    public function run(AsyncOperation $operation): void
    {
        $payload = $operation->payload ?? [];
        $format = $payload['format'] ?? 'csv';
        $columns = $payload['columns'];
        $user = $operation->user_id !== null ? User::query()->find($operation->user_id) : null;
        $path = self::DIRECTORY.'/'.$operation->uuid.'.'.$format;

        try {
            $this->operations->progress($operation, 0);
            Storage::disk(self::DISK)->makeDirectory(self::DIRECTORY);
            $absolute = Storage::disk(self::DISK)->path($path);

            $writer = $format === 'xlsx' ? new XlsxWriter : new CsvWriter(new CsvOptions(FIELD_DELIMITER: ';'));
            $writer->openToFile($absolute);
            $writer->addRow(Row::fromValues(array_map(fn (string $k) => self::neutralizeFormula(ContactColumns::label($k)), $columns)));

            $with = ['tags'];
            if (in_array('lists', $columns, true)) {
                $with[] = 'recipientLists:recipient_lists.id,recipient_lists.uuid,recipient_lists.name';
            }

            $rows = 0;
            $this->queries->selectionQuery($payload['selection'], $user)->with($with)
                ->chunkById(500, function (Collection $chunk) use ($writer, $columns, &$rows, $operation): void {
                    foreach ($chunk as $recipient) {
                        $writer->addRow(Row::fromValues($this->cells($recipient, $columns)));
                        $rows++;
                    }
                    $this->operations->progress($operation, $rows);
                }, 'recipients.id', 'id');

            $writer->close();

            $expires = now()->addDay();
            $operation->forceFill(['processed' => $rows, 'payload' => array_merge($payload, ['path' => $path])]);
            $this->operations->complete($operation, [
                'download_url' => URL::temporarySignedRoute('contacts.exports.download', $expires, ['operation' => $operation->uuid]),
                'expires_at' => $expires->utc()->format('Y-m-d\TH:i:s\Z'),
                'row_count' => $rows,
            ]);

            $this->audit->record('contacts.exported', null, $user, null, [
                'operation_id' => $operation->uuid, 'format' => $format, 'row_count' => $rows,
            ]);
        } catch (\Throwable $e) {
            $this->operations->fail($operation, $e->getMessage());
        }
    }

    /**
     * @param  list<string>  $columns
     * @return list<scalar|null>
     */
    private function cells(Recipient $recipient, array $columns): array
    {
        return array_map(function (string $key) use ($recipient) {
            $value = ContactColumns::value($recipient, $key);

            return self::neutralizeFormula(match (true) {
                is_array($value) => implode(', ', array_map(fn ($v) => is_scalar($v) ? (string) $v : json_encode($v), $value)),
                is_bool($value) => $value ? 'oui' : 'non',
                $value === null, is_scalar($value) => $value,
                default => json_encode($value),
            });
        }, $columns);
    }

    /**
     * CSV/XLSX formula injection: a text cell starting with `=`, `+`, `-`,
     * `@`, tab or CR is prefixed with `'` so spreadsheets show it as text.
     */
    public static function neutralizeFormula(mixed $value): mixed
    {
        if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }
}
