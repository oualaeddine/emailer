<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * assertSame for decoded JSON columns. MySQL's native JSON type re-orders
     * object keys (MariaDB keeps them as written), so compare with keys
     * sorted at every level while keeping values strictly typed.
     */
    protected function assertSameJson(array $expected, mixed $actual, string $message = ''): void
    {
        $this->assertIsArray($actual, $message);
        $this->assertSame(self::sortKeys($expected), self::sortKeys($actual), $message);
    }

    private static function sortKeys(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn ($item) => is_array($item) ? self::sortKeys($item) : $item, $value);
    }
}
