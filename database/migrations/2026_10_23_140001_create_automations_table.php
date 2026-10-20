<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** docs/45 §45.8 Slot R — WP-91 `automations`. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('status', 16)->default('draft')->index();
            $table->string('trigger_type', 32)->index();
            $table->json('trigger_config');
            $table->boolean('allow_reentry')->default(false);
            $table->unsignedSmallInteger('reentry_after_days')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automations');
    }
};
