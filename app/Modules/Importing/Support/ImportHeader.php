<?php

namespace App\Modules\Importing\Support;

/**
 * Resolves the header row of an imported file. Files exported from other
 * tools often have no header line at all (just one email per line): when the
 * first row already holds an email address it is data, not a header, and
 * columns get generic names ("Colonne 1", …) instead.
 */
final class ImportHeader
{
    /**
     * @param  list<string|null>  $firstRow
     * @return array{headers: list<string>, is_data: bool}
     */
    public static function resolve(array $firstRow): array
    {
        $cells = array_map(fn ($c) => trim((string) $c), $firstRow);
        if ($cells !== []) {
            $cells[0] = ltrim($cells[0], "\xEF\xBB\xBF"); // UTF-8 BOM
        }

        $isData = false;
        foreach ($cells as $cell) {
            if (self::isEmail($cell)) {
                $isData = true;
                break;
            }
        }

        $headers = $isData
            ? array_map(fn (int $i) => 'Colonne '.($i + 1), array_keys($cells))
            : self::unique($cells);

        return ['headers' => $headers, 'is_data' => $isData];
    }

    public static function isEmail(?string $value): bool
    {
        return $value !== null && filter_var(trim($value), FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Empty or duplicate headers would collapse keys in array_combine and shift
     * column indexes, so they are renamed ("Colonne 3", "email (2)").
     *
     * @param  list<string>  $headers
     * @return list<string>
     */
    private static function unique(array $headers): array
    {
        $seen = [];
        $out = [];
        foreach ($headers as $i => $header) {
            $name = $header === '' ? 'Colonne '.($i + 1) : $header;
            $base = $name;
            $n = 2;
            while (isset($seen[$name])) {
                $name = "{$base} ({$n})";
                $n++;
            }
            $seen[$name] = true;
            $out[] = $name;
        }

        return $out;
    }
}
