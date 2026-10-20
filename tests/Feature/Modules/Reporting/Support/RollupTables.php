<?php

namespace Tests\Feature\Modules\Reporting\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Test-only mirror of the WP-70 tables (docs/45 §45.8 Slot L), created only when the real migrations are absent.
 *
 * DDL runs on a second connection so that it does not implicitly commit the transaction opened by
 * RefreshDatabase on the default one (MariaDB/MySQL).
 */
final class RollupTables
{
    private const COUNTERS = ['sent', 'delivered', 'opens', 'unique_opens', 'proxy_opens', 'clicks', 'unique_clicks',
        'hard_bounces', 'soft_bounces', 'complaints', 'unsubscriptions', 'deferred', 'blocked'];

    public static function ensure(): void
    {
        $name = config('database.default');
        config(['database.connections.reporting_ddl' => config("database.connections.{$name}")]);
        DB::purge('reporting_ddl');
        $schema = Schema::connection('reporting_ddl');

        if (! Schema::hasTable('campaign_links')) {
            $schema->create('campaign_links', function (Blueprint $t): void {
                $t->id();
                $t->uuid('uuid')->unique();
                $t->unsignedBigInteger('campaign_id');
                $t->string('link_id', 16);
                $t->text('url');
                $t->char('url_hash', 40)->index();
                $t->string('label', 255)->nullable();
                $t->unsignedInteger('unique_clicks')->default(0);
                $t->unsignedInteger('total_clicks')->default(0);
                $t->timestamp('last_clicked_at')->nullable();
                $t->timestamps();
                $t->unique(['campaign_id', 'link_id']);
            });
        }

        if (! Schema::hasTable('campaign_stats_hourly')) {
            $schema->create('campaign_stats_hourly', function (Blueprint $t): void {
                $t->unsignedBigInteger('campaign_id');
                $t->timestamp('hour')->useCurrent();
                $t->unsignedBigInteger('variant_id')->nullable();
                foreach (self::COUNTERS as $c) {
                    $t->unsignedInteger($c)->default(0);
                }
                $t->timestamps();
                $t->unique(['campaign_id', 'hour', 'variant_id']);
            });
        }

        if (! Schema::hasTable('campaign_stats_by_list')) {
            $schema->create('campaign_stats_by_list', function (Blueprint $t): void {
                $t->unsignedBigInteger('campaign_id');
                $t->string('target_type', 16);
                $t->unsignedBigInteger('target_id');
                foreach (self::COUNTERS as $c) {
                    $t->unsignedInteger($c)->default(0);
                }
                $t->timestamps();
                $t->unique(['campaign_id', 'target_type', 'target_id']);
            });
        }

        if (! Schema::hasTable('message_events')) {
            $schema->create('message_events', function (Blueprint $t): void {
                $t->id();
                $t->unsignedBigInteger('message_id')->index();
                $t->string('event_type', 32);
                $t->timestamp('occurred_at')->nullable();
                $t->json('metadata')->nullable();
                $t->text('url')->nullable();
                $t->string('link_id', 16)->nullable();
                $t->string('user_agent', 512)->nullable();
                $t->string('device_type', 16)->nullable();
                $t->string('email_client', 64)->nullable();
                $t->char('ip_country', 2)->nullable();
                $t->string('ip_city', 100)->nullable();
                $t->boolean('is_proxy_open')->default(false);
                $t->timestamps();
            });
        }

        DB::purge('reporting_ddl');
    }
}
