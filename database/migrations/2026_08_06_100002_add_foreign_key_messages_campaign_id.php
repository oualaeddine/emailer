<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backfills the FK deferred by 2026_07_14_170000 (messages.campaign_id had
 * no `campaigns` table to reference yet at Tracking module build time) —
 * mirrors the exact pattern of
 * 2026_07_14_170002_add_foreign_key_send_attempts_message_id.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table): void {
            $table->foreign('campaign_id', 'messages_campaign_id_foreign')
                ->references('id')->on('campaigns')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table): void {
            $table->dropForeign('messages_campaign_id_foreign');
        });
    }
};
