<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/04-database-design.md §4.6 — `import_errors`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_errors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('import_job_id')->constrained('import_jobs')->cascadeOnDelete();
            $table->unsignedInteger('row_number')->nullable();
            $table->string('error_code', 50);
            $table->text('message');
            $table->timestampTz('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_errors');
    }
};
