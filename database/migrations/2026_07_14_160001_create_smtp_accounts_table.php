<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/04-database-design.md §4.9 — `smtp_accounts`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('smtp_accounts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 150);
            $table->string('provider', 50);
            $table->string('host', 255);
            $table->unsignedSmallInteger('port');
            $table->string('encryption', 10);
            $table->string('username', 255);
            $table->text('password_encrypted');
            $table->string('from_email', 191);
            $table->string('from_name', 150)->nullable();
            $table->unsignedInteger('daily_quota')->nullable();
            $table->unsignedInteger('hourly_quota')->nullable();
            $table->unsignedInteger('minute_quota')->nullable();
            $table->unsignedInteger('max_concurrent_connections')->default(1);
            $table->unsignedInteger('max_messages_per_connection')->nullable();
            $table->integer('priority')->default(100);
            $table->unsignedInteger('rotation_weight')->default(1);
            $table->boolean('warmup_enabled')->default(false);
            $table->foreignId('warmup_schedule_id')->nullable()->constrained('warmup_schedules')->nullOnDelete();
            $table->string('health_status', 20)->default('healthy');
            $table->decimal('bounce_rate', 5, 2)->default(0);
            $table->decimal('complaint_rate', 5, 2)->default(0);
            $table->decimal('reputation_score', 5, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampTz('last_tested_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smtp_accounts');
    }
};
