<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/45-brevo-api-contracts.md §45.8 Slot O (WP-80) — `messages.variant_id`:
 * the A/B variant a test-sample message carried (null for classic campaigns
 * and for the remainder sent with the winner's content).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table): void {
            $table->foreignId('variant_id')->nullable()->constrained('ab_test_variants')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table): void {
            $table->dropForeign(['variant_id']);
            $table->dropColumn('variant_id');
        });
    }
};
