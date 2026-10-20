<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/45-brevo-api-contracts.md §45.8 Slot O (WP-80) — `ab_test_variants`:
 * the two versions (A, B) of an A/B test campaign (docs/43 §43.6.2).
 * `sample_size` = number of test-sample recipients assigned to the variant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ab_test_variants', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->char('label', 1);
            $table->string('subject', 255)->nullable();
            $table->string('preview_text', 255)->nullable();
            $table->longText('html_body')->nullable();
            $table->json('design_json')->nullable();
            $table->boolean('is_winner')->default(false);
            $table->unsignedInteger('sample_size')->default(0);
            $table->timestamps();

            $table->unique(['campaign_id', 'label']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ab_test_variants');
    }
};
