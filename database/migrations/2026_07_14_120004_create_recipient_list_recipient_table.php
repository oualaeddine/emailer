<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/04-database-design.md §4.5 — `recipient_list_recipient` pivot
 * (static lists only).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipient_list_recipient', function (Blueprint $table): void {
            $table->foreignId('recipient_list_id')->constrained('recipient_lists')->cascadeOnDelete();
            $table->foreignId('recipient_id')->constrained('recipients')->cascadeOnDelete();
            $table->timestampTz('added_at')->useCurrent();
            $table->primary(['recipient_list_id', 'recipient_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipient_list_recipient');
    }
};
