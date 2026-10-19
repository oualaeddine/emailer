<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * docs/43-brevo-parity-plan.md §43.8 "Migration of existing data" /
 * docs/45-brevo-api-contracts.md §45.8 Slot B — one `include` row in
 * `campaign_audiences` from each campaign's legacy `recipient_list_id` /
 * `segment_id`. The legacy columns stay (nullable) for compatibility
 * (§45.10). Idempotent (insertOrIgnore on the unique key).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('campaigns')
            ->select(['id', 'recipient_list_id', 'segment_id'])
            ->where(fn ($q) => $q->whereNotNull('recipient_list_id')->orWhereNotNull('segment_id'))
            ->orderBy('id')
            ->chunkById(500, function ($campaigns): void {
                $now = now();
                $rows = [];

                foreach ($campaigns as $campaign) {
                    if ($campaign->recipient_list_id !== null) {
                        $rows[] = [
                            'campaign_id' => $campaign->id, 'target_type' => 'list',
                            'target_id' => $campaign->recipient_list_id, 'mode' => 'include',
                            'created_at' => $now, 'updated_at' => $now,
                        ];
                    }
                    if ($campaign->segment_id !== null) {
                        $rows[] = [
                            'campaign_id' => $campaign->id, 'target_type' => 'segment',
                            'target_id' => $campaign->segment_id, 'mode' => 'include',
                            'created_at' => $now, 'updated_at' => $now,
                        ];
                    }
                }

                if ($rows !== []) {
                    DB::table('campaign_audiences')->insertOrIgnore($rows);
                }
            });
    }

    public function down(): void
    {
        // Data backfill — nothing to undo (rows are dropped with the table).
    }
};
