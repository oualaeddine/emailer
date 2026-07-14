<?php

namespace App\Modules\Importing\Services;

use Generator;
use RuntimeException;

/**
 * docs/14-import-export.md §14.3 — CSV Import.
 * Streams the file (generator-based) rather than loading it fully into
 * memory, per docs/03-folder-structure.md §3.4's memory-safety
 * requirement for large files.
 */
class CsvImportService
{
    /**
     * @return Generator<int, array<string, string>>
     */
    public function parse(string $absolutePath): Generator
    {
        $handle = fopen($absolutePath, 'rb');

        if ($handle === false) {
            throw new RuntimeException("Impossible de lire le fichier CSV : {$absolutePath}");
        }

        try {
            $delimiter = $this->detectDelimiter($handle);
            $header = fgetcsv($handle, 0, $delimiter);

            if ($header === false) {
                return;
            }

            $header = array_map(fn (?string $h) => trim((string) $h), $header);

            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                if ($row === [null] || $row === false) {
                    continue;
                }

                // Pad/truncate to header length so short/long rows never crash array_combine.
                $row = array_pad(array_slice($row, 0, count($header)), count($header), null);

                yield array_combine($header, $row);
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * docs/14-import-export.md §14.3 — delimiter auto-detection.
     *
     * @param  resource  $handle
     */
    private function detectDelimiter($handle): string
    {
        $firstLine = fgets($handle);
        rewind($handle);

        if ($firstLine === false) {
            return ',';
        }

        $candidates = [',', ';', "\t"];
        $counts = array_map(fn (string $delimiter) => substr_count($firstLine, $delimiter), $candidates);

        return $candidates[array_search(max($counts), $counts, true)];
    }
}
