<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Campaigns sent before the Brevo-parity programme have no
 * `campaign_recipients` rows, so `recipients_count` (added by
 * 2026_10_20_110002) stayed 0 and the campaign list and report showed
 * "0 destinataires" for every historical campaign. Backfill it from the
 * campaign's non-test messages. AudienceResolver overwrites the value
 * whenever it materialises an audience, so a later resume stays correct.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('campaigns')
            ->where('recipients_count', 0)
            ->whereNotIn('status', ['draft', 'scheduled'])
            ->update([
                'recipients_count' => DB::raw(
                    '(SELECT COUNT(DISTINCT m.recipient_id) FROM messages m'
                    .' WHERE m.campaign_id = campaigns.id AND m.is_test = 0)'
                ),
            ]);
    }

    public function down(): void
    {
        // Data backfill only; the previous zeros carried no information.
    }
};
