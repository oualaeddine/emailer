<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/04-database-design.md §4.4 — `template_versions`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('template_id')->constrained('templates')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->longText('html_content');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('change_note', 255)->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['template_id', 'version_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_versions');
    }
};
