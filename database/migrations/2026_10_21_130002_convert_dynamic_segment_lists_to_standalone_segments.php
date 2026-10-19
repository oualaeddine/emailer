<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * docs/45-brevo-api-contracts.md §45.8 Slot G — conversion: every
 * `recipient_lists.type = dynamic_segment` becomes a standalone segment that
 * carries its own name, description and creator. The list row is kept
 * (read-only legacy, §45.10) and stays linked through the nullable
 * `segments.recipient_list_id`. Dynamic lists without a rule row are left
 * alone (an empty rule tree would mean "all contacts").
 * Idempotent: re-running only refreshes the copied fields.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('recipient_lists')
            ->where('type', 'dynamic_segment')
            ->orderBy('id')
            ->chunkById(500, function ($lists): void {
                foreach ($lists as $list) {
                    DB::table('segments')->where('recipient_list_id', $list->id)->update([
                        'name' => mb_substr((string) $list->name, 0, 150),
                        'description' => $list->description !== null ? mb_substr((string) $list->description, 0, 500) : null,
                        'created_by' => $list->created_by,
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Data conversion only: the standalone columns are dropped by 130001's down().
    }
};
