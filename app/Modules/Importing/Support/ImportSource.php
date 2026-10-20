<?php

namespace App\Modules\Importing\Support;

/**
 * docs/45-brevo-api-contracts.md §45.5.7 — wizard source types. Stored in
 * `import_jobs.source_type`: `xlsx` is persisted as the legacy `excel`.
 * (The shared ImportSourceType enum is outside this work package.)
 */
final class ImportSource
{
    public const CSV = 'csv';
    public const EXCEL = 'excel';
    public const TXT = 'txt';
    public const PASTE = 'paste';
    public const PAGEJAUNES = 'pagejaunes';

    /** Accepted request values (legacy `excel` + contract `xlsx`). */
    public const REQUEST_VALUES = ['csv', 'xlsx', 'excel', 'txt', 'paste', 'pagejaunes'];

    public static function normalise(string $requested): string
    {
        return $requested === 'xlsx' ? self::EXCEL : $requested;
    }

    /** Sources parsed by the delimiter-detecting text reader. */
    public static function isDelimited(string $stored): bool
    {
        return in_array($stored, [self::CSV, self::TXT, self::PASTE, self::PAGEJAUNES], true);
    }
}
