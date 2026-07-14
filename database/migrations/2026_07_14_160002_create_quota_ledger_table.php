<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/04-database-design.md §4.9 — `quota_ledger`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quota_ledger', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('smtp_account_id')->constrained('smtp_accounts')->cascadeOnDelete();
            $table->string('window_type', 10);
            $table->timestampTz('window_start');
            $table->unsignedInteger('sent_count')->default(0);
            $table->timestamps();

            $table->unique(['smtp_account_id', 'window_type', 'window_start'], 'quota_ledger_unique_window');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quota_ledger');
    }
};
