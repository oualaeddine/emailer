<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** docs/45 §45.8 Slot R — WP-91 `automation_runs` (one contact's journey through an automation). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('automation_id')->constrained('automations')->cascadeOnDelete();
            $table->foreignId('recipient_id')->constrained('recipients')->cascadeOnDelete();
            $table->string('status', 16)->index();
            $table->foreignId('current_step_id')->nullable()->constrained('automation_steps')->nullOnDelete();
            $table->timestamp('next_run_at')->nullable()->index();
            $table->json('context')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['automation_id', 'recipient_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_runs');
    }
};
