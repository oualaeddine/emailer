<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/04-database-design.md §4.7 — `pagejaunes_company_emails_cache`.
 * Mirrors the source's one-to-many `emails` child table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagejaunes_company_emails_cache', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pagejaunes_company_cache_id');
            $table->foreign('pagejaunes_company_cache_id', 'pj_company_emails_cache_company_fk')
                ->references('id')->on('pagejaunes_company_cache')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('pagejaunes_email_id')->unique();
            $table->string('email', 191);
            $table->timestampTz('source_deleted_at')->nullable();
            $table->timestampTz('synced_at')->nullable();

            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagejaunes_company_emails_cache');
    }
};
