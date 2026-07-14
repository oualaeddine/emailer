<?php

namespace App\Modules\Importing\Services;

use Generator;
use OpenSpout\Reader\XLSX\Reader;

/**
 * docs/14-import-export.md §14.4 — Excel Import.
 * Uses a streaming spreadsheet reader (OpenSpout) rather than loading the
 * entire workbook into memory, per the documented memory-safety
 * requirement. Only `.xlsx` is supported (legacy `.xls` is explicitly out
 * of scope per §14.4).
 */
class ExcelImportService
{
    /**
     * @return list<string>
     */
    public function listSheetNames(string $absolutePath): array
    {
        $reader = new Reader();
        $reader->open($absolutePath);

        $names = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            $names[] = $sheet->getName();
        }

        $reader->close();

        return $names;
    }

    /**
     * @return Generator<int, array<string, string>>
     */
    public function parse(string $absolutePath, ?string $sheetName = null): Generator
    {
        $reader = new Reader();
        $reader->open($absolutePath);

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                if ($sheetName !== null && $sheet->getName() !== $sheetName) {
                    continue;
                }

                $header = null;

                foreach ($sheet->getRowIterator() as $row) {
                    $cells = array_map(
                        fn ($cell) => $cell === null ? null : (string) $cell,
                        $row->toArray(),
                    );

                    if ($header === null) {
                        $header = array_map(fn (?string $h) => trim((string) $h), $cells);

                        continue;
                    }

                    $cells = array_pad(array_slice($cells, 0, count($header)), count($header), null);

                    yield array_combine($header, $cells);
                }

                if ($sheetName !== null) {
                    break;
                }
            }
        } finally {
            $reader->close();
        }
    }
}
