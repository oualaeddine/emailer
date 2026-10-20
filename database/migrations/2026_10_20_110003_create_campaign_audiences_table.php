<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/45-brevo-api-contracts.md §45.8 Slot B — `campaign_audiences`
 * (include/exclude × list/segment, docs/43-brevo-parity-plan.md §43.6.1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_audiences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->string('target_type', 16);
            $table->unsignedBigInteger('target_id');
            $table->string('mode', 16);
            $table->timestamps();

            $table->unique(['campaign_id', 'target_type', 'target_id']);
            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_audiences');
    }
};
