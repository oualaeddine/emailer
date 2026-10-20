<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** docs/45 §45.8 Slot R — WP-91 `automation_steps` (tree: condition steps own yes/no branches). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_steps', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('automation_id')->constrained('automations')->cascadeOnDelete();
            $table->foreignId('parent_step_id')->nullable()->constrained('automation_steps')->cascadeOnDelete();
            $table->string('branch', 8)->nullable();
            $table->unsignedSmallInteger('position');
            $table->string('type', 32);
            $table->json('config');
            $table->timestamps();

            $table->index(['automation_id', 'parent_step_id', 'branch', 'position'], 'automation_steps_tree_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_steps');
    }
};
