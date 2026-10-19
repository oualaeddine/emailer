<?php

namespace App\Modules\Importing\Services;

use App\Modules\Importing\Models\ImportJob;
use App\Modules\Importing\Support\ImportSource;
use Generator;

/**
 * docs/45-brevo-api-contracts.md §45.5.7 — `ImportPreview`: headers, first
 * five rows, total row count, delimiter and a header-based suggested mapping.
 */
class ImportPreviewService
{
    public function __construct(
        private readonly ImportJobService $jobs,
        private readonly CsvImportService $csv,
        private readonly ExcelImportService $excel,
        private readonly ImportAttributeCatalog $catalog,
    ) {
    }

    /**
     * @return array{headers: list<string>, rows: list<list<string>>, total_rows: int, delimiter: string|null, suggested_mapping: list<array<string, mixed>>}
     */
    public function build(ImportJob $job): array
    {
        $path = $this->jobs->absolutePath($job);

        $headers = [];
        $rows = [];
        $total = 0;

        foreach ($this->rows($job, $path) as $row) {
            if ($total === 0) {
                $headers = array_map('strval', array_keys($row));
            }
            if ($total < 5) {
                $rows[] = array_map(fn ($v) => (string) ($v ?? ''), array_values($row));
            }
            $total++;
        }

        $suggested = [];
        $emailTaken = false;
        foreach ($headers as $index => $header) {
            $attribute = $this->catalog->guess($header);

            if ($attribute === 'EMAIL') {
                if ($emailTaken) {
                    $attribute = null;
                }
                $emailTaken = true;
            }

            $suggested[] = [
                'column' => $index,
                'header' => $header,
                'target' => $attribute === null ? ['type' => 'ignore'] : ['type' => 'attribute', 'attribute' => $attribute],
            ];
        }

        return [
            'headers' => $headers,
            'rows' => $rows,
            'total_rows' => $total,
            'delimiter' => ImportSource::isDelimited($job->source_type) ? $this->csv->detectDelimiterOf($path) : null,
            'suggested_mapping' => $suggested,
        ];
    }

    /**
     * @return Generator<int, array<string, string|null>>
     */
    private function rows(ImportJob $job, string $path): Generator
    {
        yield from ($job->source_type === ImportSource::EXCEL ? $this->excel->parse($path) : $this->csv->parse($path));
    }
}
