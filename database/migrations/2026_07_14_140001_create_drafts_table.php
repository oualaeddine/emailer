<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/04-database-design.md §4.3 — Composer Module: `drafts`.
 * `template_id` is a plain nullable column (no FK constraint yet) since
 * the `templates` table does not exist until Module 7 — the constraint
 * is added by that module's migration (docs/34-roadmap.md build order).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drafts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('template_id')->nullable();
            $table->string('subject', 998)->nullable();
            $table->longText('html_body')->nullable();
            $table->foreignId('signature_id')->nullable()->constrained('signatures')->nullOnDelete();
            $table->string('status', 20)->default('draft');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drafts');
    }
};
