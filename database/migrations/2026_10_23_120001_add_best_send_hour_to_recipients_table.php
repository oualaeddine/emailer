<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/45-brevo-api-contracts.md §45.8 Slot P (WP-81 send at best time) —
 * the nightly precompute of each contact's best hour of week (0–167, Monday
 * 00h = 0, account timezone; `ComputeBestSendHoursJob`).
 *
 * `messages.scheduled_for` is deliberately NOT created: the dispatcher only
 * creates a `Message` once its `campaign_recipients` row is due, so the
 * existing `campaign_recipients.scheduled_for` (Slot B) already carries the
 * per-contact release time (Slot P "skip and reuse").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recipients', function (Blueprint $table): void {
            if (! Schema::hasColumn('recipients', 'best_send_hour')) {
                $table->unsignedSmallInteger('best_send_hour')->nullable();
            }
            if (! Schema::hasColumn('recipients', 'best_send_hour_computed_at')) {
                $table->timestamp('best_send_hour_computed_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('recipients', function (Blueprint $table): void {
            foreach (['best_send_hour', 'best_send_hour_computed_at'] as $column) {
                if (Schema::hasColumn('recipients', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
