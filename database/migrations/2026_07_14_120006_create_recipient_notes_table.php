<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/16-email-history.md §16.2 — `recipient_notes` (schema addendum).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipient_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('recipient_id')->constrained('recipients')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestampTz('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipient_notes');
    }
};
