<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** docs/45 §45.8 Slot R — WP-91 `automation_step_logs` (one row per step a run went through). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_step_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('automation_run_id')->constrained('automation_runs')->cascadeOnDelete();
            $table->foreignId('automation_step_id')->constrained('automation_steps')->cascadeOnDelete();
            $table->string('outcome', 16);
            $table->foreignId('message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_step_logs');
    }
};
