<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * docs/45-brevo-api-contracts.md §45.8 Slot E (WP-52) — per-contact engagement
 * counters and consent columns on `recipients`, plus a one-off backfill of the
 * counters from `messages`.
 */
return new class extends Migration
{
    private const COUNTERS = [
        'messages_sent', 'delivered', 'opened', 'clicked',
        'hard_bounces', 'soft_bounces', 'complaints', 'unsubscriptions',
    ];

    public function up(): void
    {
        Schema::table('recipients', function (Blueprint $table): void {
            $table->string('ext_id', 100)->nullable()->unique();
            $table->timestamp('opt_in_at')->nullable();
            $table->string('opt_in_source', 32)->nullable();
            $table->timestamp('last_opened_at')->nullable()->index();
            $table->timestamp('last_clicked_at')->nullable()->index();
            $table->unsignedTinyInteger('engagement_score')->nullable();
            foreach (self::COUNTERS as $column) {
                $table->unsignedInteger($column)->default(0);
            }
            $table->timestamp('counters_updated_at')->nullable();
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::table('recipients', function (Blueprint $table): void {
            $table->dropUnique(['ext_id']);
            $table->dropIndex(['last_opened_at']);
            $table->dropIndex(['last_clicked_at']);
            $table->dropColumn([
                'ext_id', 'opt_in_at', 'opt_in_source', 'last_opened_at', 'last_clicked_at',
                'engagement_score', ...self::COUNTERS, 'counters_updated_at',
            ]);
        });
    }

    /**
     * Counters are derived from `messages` (test sends excluded when the
     * `is_test` column exists). `message_events` is not read here: it is
     * created by a later slot and the message timestamps already carry the
     * first open / click.
     */
    private function backfill(): void
    {
        if (! Schema::hasTable('messages')) {
            return;
        }

        $real = Schema::hasColumn('messages', 'is_test') ? ' AND m.is_test = false' : '';
        $count = fn (string $condition): string => "(SELECT COUNT(*) FROM messages m WHERE m.recipient_id = recipients.id AND {$condition}{$real})";
        $max = fn (string $column): string => "(SELECT MAX(m.{$column}) FROM messages m WHERE m.recipient_id = recipients.id{$real})";

        DB::table('recipients')->update([
            'messages_sent' => DB::raw($count('m.sent_at IS NOT NULL')),
            'delivered' => DB::raw($count('m.delivered_at IS NOT NULL')),
            'opened' => DB::raw($count('m.opened_at IS NOT NULL')),
            'clicked' => DB::raw($count('m.clicked_at IS NOT NULL')),
            'hard_bounces' => DB::raw($count("m.status = 'hard_bounced'")),
            'soft_bounces' => DB::raw($count("m.status = 'soft_bounced'")),
            'complaints' => DB::raw($count("m.status = 'spam_complaint'")),
            'unsubscriptions' => DB::raw($count("m.status = 'unsubscribed'")),
            'last_opened_at' => DB::raw($max('opened_at')),
            'last_clicked_at' => DB::raw($max('clicked_at')),
            'counters_updated_at' => now(),
        ]);
    }
};
