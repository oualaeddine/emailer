<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/04-database-design.md §4.3 — `draft_versions`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('draft_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('draft_id')->constrained('drafts')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->longText('html_body')->nullable();
            $table->string('subject', 998)->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['draft_id', 'version_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('draft_versions');
    }
};
