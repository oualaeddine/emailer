<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Before the Brevo-parity programme, contacts carried free-form keys in
 * `recipients.custom_fields` (imports mapped columns such as "wilaya" or
 * "secteur" there). WP-50 introduced typed attribute definitions but only
 * seeded the system ones, so every legacy key was invisible in the new
 * contacts table, contact detail, filters and merge tags.
 *
 * Register one `text` attribute per legacy key, under the key's exact name,
 * so the stored values are read in place (no data is moved, and legacy
 * segment rules that point at `custom_fields.<key>` keep matching).
 * Keys that are not plain identifiers are left as they are.
 */
return new class extends Migration
{
    public function up(): void
    {
        $existing = DB::table('contact_attributes')->pluck('name')
            ->map(fn ($n) => mb_strtolower((string) $n))->all();
        $keys = [];

        DB::table('recipients')
            ->whereNotNull('custom_fields')
            ->select(['id', 'custom_fields'])
            ->orderBy('id')
            ->chunkById(1000, function ($rows) use (&$keys): void {
                foreach ($rows as $row) {
                    $fields = json_decode((string) $row->custom_fields, true);
                    if (! is_array($fields)) {
                        continue;
                    }
                    foreach (array_keys($fields) as $key) {
                        if (is_string($key) && preg_match('/^[A-Za-z][A-Za-z0-9_]{0,49}$/', $key) === 1) {
                            $keys[$key] = true;
                        }
                    }
                }
            });

        $position = (int) DB::table('contact_attributes')->where('is_system', false)->max('position');
        $now = now();

        foreach (array_keys($keys) as $key) {
            if (in_array(mb_strtolower($key), $existing, true)) {
                continue;
            }
            $existing[] = mb_strtolower($key);
            DB::table('contact_attributes')->insert([
                'uuid' => (string) Str::uuid(),
                'name' => $key,
                'label' => Str::ucfirst(str_replace('_', ' ', $key)),
                'type' => 'text',
                'options' => null,
                'is_system' => false,
                'storage' => 'custom_fields',
                'column' => null,
                'position' => ++$position,
                'created_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Definitions may have been edited or referenced since; keep them.
    }
};
