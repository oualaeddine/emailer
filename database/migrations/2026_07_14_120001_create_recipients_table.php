<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/04-database-design.md §4.5 — Recipients Module: `recipients`.
 * `email` is a plain string, not Postgres `citext` — MariaDB/MySQL's
 * default `utf8mb4_unicode_ci` collation is already case-insensitive for
 * comparisons/uniqueness, matching the documented case-insensitive intent
 * without the Postgres-only extension (see docs/04 §4.1 conventions note,
 * same portability approach already used for `users.email`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipients', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('email', 191)->unique();
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('company_name', 255)->nullable();
            $table->foreignId('pagejaunes_company_cache_id')
                ->nullable()
                ->constrained('pagejaunes_company_cache')
                ->nullOnDelete();
            $table->string('source', 30);
            $table->string('status', 20)->default('active');
            $table->json('custom_fields')->nullable();
            $table->timestampTz('last_contacted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->fullText(['company_name'], 'recipients_company_name_ft');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipients');
    }
};
