<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/04-database-design.md §4.11 — Verification Module: `verification_results`.
 * docs/42-parallel-execution-plan.md §42.10 — migration slot C (`2026_08_06_3000xx`).
 *
 * Keyed by `email` (not `recipient_id`) per docs/22-email-verification.md
 * §22.5 — "`verification_results` rows are keyed by email with
 * `checked_at`/`expires_at`" — so a non-expired cached result can be reused
 * for any recipient sharing that email address without a foreign key tying
 * the cache to one specific `recipients` row. Append-only history (doc 22
 * §22.6): every check inserts a new row rather than updating one in place,
 * so a full audit trail per email is retained.
 *
 * No new columns were added to `recipients` for this work package: its
 * `status` column (docs/04-database-design.md §4.5) already has an
 * `invalid` value in its CHECK list, which is all doc 22 §22.5 requires
 * ("`recipients.status` is updated to `invalid` when a fresh verification
 * returns `undeliverable`") — the richer per-check verdict/history data
 * lives here instead, exactly as doc 04 §4.11 specifies.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verification_results', function (Blueprint $table): void {
            $table->id();
            $table->string('email', 191);
            $table->boolean('syntax_valid');
            $table->boolean('mx_valid')->nullable();
            $table->boolean('is_disposable')->nullable();
            $table->boolean('is_role_based')->nullable();
            $table->boolean('is_catch_all')->nullable();
            $table->string('verdict', 20);
            $table->string('provider', 50)->nullable();
            $table->json('raw_response')->nullable();
            $table->timestampTz('checked_at');
            $table->timestampTz('expires_at')->nullable();

            // docs/04-database-design.md §4.11 — "(email, checked_at desc) —
            // latest result per email is `expires_at > now()` lookup, forms
            // the verification cache."
            $table->index(['email', 'checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_results');
    }
};
