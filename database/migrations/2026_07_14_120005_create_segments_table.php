<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/04-database-design.md §4.5 — `segments`. 1:1 with a `dynamic_segment`
 * recipient_lists row (unique recipient_list_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('segments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('recipient_list_id')->unique()->constrained('recipient_lists')->cascadeOnDelete();
            $table->json('rules');
            $table->timestampTz('last_evaluated_at')->nullable();
            $table->integer('cached_count')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('segments');
    }
};
