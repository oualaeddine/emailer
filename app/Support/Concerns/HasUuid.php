<?php

namespace App\Support\Concerns;

use Illuminate\Support\Str;

/**
 * Generates a UUID for the model's `uuid` column at creation time.
 *
 * docs/04-database-design.md §4.1 specifies `uuid UNIQUE NOT NULL DEFAULT
 * gen_random_uuid()`, a PostgreSQL-only default. Since local development
 * targets MariaDB (no `gen_random_uuid()`), the UUID is generated in the
 * application layer instead — functionally identical from every caller's
 * perspective (the column is always populated before the row is persisted)
 * and portable across both database engines.
 */
trait HasUuid
{
    protected static function bootHasUuid(): void
    {
        static::creating(function ($model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }
}
