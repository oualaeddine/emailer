<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/04-database-design.md §4.5 — `recipient_tag` pivot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipient_tag', function (Blueprint $table): void {
            $table->foreignId('recipient_id')->constrained('recipients')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();
            $table->primary(['recipient_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipient_tag');
    }
};
