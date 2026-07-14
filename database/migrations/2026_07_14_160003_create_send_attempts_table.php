<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/04-database-design.md §4.9 — `send_attempts`. `message_id` is a
 * plain column (no FK yet) since `messages` does not exist until the
 * Tracking module — the constraint is added by that module's migration
 * (docs/34-roadmap.md build order: Delivery Engine before Tracking).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('send_attempts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('message_id');
            $table->foreignId('smtp_account_id')->constrained('smtp_accounts')->restrictOnDelete();
            $table->unsignedInteger('attempt_number');
            $table->string('status', 20);
            $table->text('provider_response')->nullable();
            $table->string('error_code', 100)->nullable();
            $table->timestampTz('attempted_at');
            $table->unsignedInteger('duration_ms')->nullable();
            $table->boolean('is_test')->default(false);

            $table->index('message_id');
            $table->index(['smtp_account_id', 'attempted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('send_attempts');
    }
};
