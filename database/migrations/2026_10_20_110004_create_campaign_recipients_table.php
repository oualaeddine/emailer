<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/45-brevo-api-contracts.md §45.8 Slot B — `campaign_recipients`, the
 * materialised audience `DispatchCampaignJob` iterates (§43.6.1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_recipients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->foreignId('recipient_id')->constrained('recipients')->cascadeOnDelete();
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->foreignId('message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->string('status', 32)->default('pending');
            $table->string('skip_reason', 64)->nullable();
            $table->timestampTz('scheduled_for')->nullable();
            $table->timestampTz('materialised_at')->useCurrent();

            $table->unique(['campaign_id', 'recipient_id']);
            $table->index(['campaign_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_recipients');
    }
};
