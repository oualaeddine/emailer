<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/45-brevo-api-contracts.md §45.8 Slot O `add_foreign_key_ab` —
 * `campaigns.winner_variant_id` and `campaign_recipients.variant_id` (both
 * created by slot B without constraint) → `ab_test_variants.id`, null on delete.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table): void {
            $table->foreign('winner_variant_id')->references('id')->on('ab_test_variants')->nullOnDelete();
        });

        Schema::table('campaign_recipients', function (Blueprint $table): void {
            $table->foreign('variant_id')->references('id')->on('ab_test_variants')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('campaign_recipients', function (Blueprint $table): void {
            $table->dropForeign(['variant_id']);
        });

        Schema::table('campaigns', function (Blueprint $table): void {
            $table->dropForeign(['winner_variant_id']);
        });
    }
};
