<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** docs/45 §45.8 Slot S — WP-101 `api_webhooks`. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_webhooks', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('url', 2048);
            $table->string('description', 255)->nullable();
            $table->string('type', 16);
            $table->json('events');
            $table->text('secret');
            $table->text('headers')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('failure_count')->default(0);
            $table->timestamp('last_delivery_at')->nullable();
            $table->string('last_delivery_status', 16)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_webhooks');
    }
};
