<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/45-brevo-api-contracts.md §45.8 Slot L (WP-70) — `message_events`,
 * the per-message engagement / delivery audit trail (docs/21 §21.4). Slot L
 * lists it as an "alter"; the base table did not exist on this branch, so it
 * is created here (guarded) and the Slot L columns are added by the next
 * migration of the slot.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('message_events')) {
            return;
        }

        Schema::create('message_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('message_id')->constrained('messages')->cascadeOnDelete();
            $table->unsignedBigInteger('campaign_id')->nullable()->index();
            $table->unsignedBigInteger('recipient_id')->nullable()->index();
            $table->string('event_type', 32)->index();
            $table->timestamp('occurred_at')->useCurrent()->index();
            $table->string('ip', 45)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_events');
    }
};
