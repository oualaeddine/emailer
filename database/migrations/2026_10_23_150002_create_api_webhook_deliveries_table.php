<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** docs/45 §45.8 Slot S — WP-101 `api_webhook_deliveries` (pruned after 30 days). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_webhook_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('api_webhook_id')->constrained('api_webhooks')->cascadeOnDelete();
            $table->string('event', 32)->index();
            $table->string('status', 16)->index();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->json('payload');
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->string('response_body_excerpt', 1000)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('next_retry_at')->nullable()->index();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['api_webhook_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_webhook_deliveries');
    }
};
