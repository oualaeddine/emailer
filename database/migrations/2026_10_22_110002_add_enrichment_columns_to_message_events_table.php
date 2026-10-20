<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/45-brevo-api-contracts.md §45.8 Slot L (WP-70) — enrichment columns
 * on `message_events`: clicked URL / link id, parsed user agent (device type,
 * e-mail client), GeoLite2 country / city and the Apple Mail Privacy
 * Protection proxy-open flag (docs/43 §43.6.3, §43.6.5).
 */
return new class extends Migration
{
    /** @return array<string, callable(Blueprint): mixed> */
    private function columns(): array
    {
        return [
            'url' => fn (Blueprint $t) => $t->text('url')->nullable(),
            'link_id' => fn (Blueprint $t) => $t->string('link_id', 16)->nullable(),
            'user_agent' => fn (Blueprint $t) => $t->string('user_agent', 512)->nullable(),
            'device_type' => fn (Blueprint $t) => $t->string('device_type', 16)->nullable(),
            'email_client' => fn (Blueprint $t) => $t->string('email_client', 64)->nullable(),
            'ip_country' => fn (Blueprint $t) => $t->char('ip_country', 2)->nullable(),
            'ip_city' => fn (Blueprint $t) => $t->string('ip_city', 100)->nullable(),
            'is_proxy_open' => fn (Blueprint $t) => $t->boolean('is_proxy_open')->default(false),
        ];
    }

    public function up(): void
    {
        Schema::table('message_events', function (Blueprint $table): void {
            foreach ($this->columns() as $name => $define) {
                if (! Schema::hasColumn('message_events', $name)) {
                    $define($table);
                }
            }
        });

        if (! Schema::hasIndex('message_events', ['message_id', 'event_type'])) {
            Schema::table('message_events', function (Blueprint $table): void {
                $table->index(['message_id', 'event_type']);
            });
        }
    }

    public function down(): void
    {
        Schema::table('message_events', function (Blueprint $table): void {
            $table->dropIndex(['message_id', 'event_type']);
        });

        Schema::table('message_events', function (Blueprint $table): void {
            $table->dropColumn(array_keys($this->columns()));
        });
    }
};
