<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/04-database-design.md §4.6 — `import_rows`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('import_job_id')->constrained('import_jobs')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->json('raw_data');
            $table->string('parsed_email', 255)->nullable();
            $table->string('validation_status', 20)->default('valid');
            $table->foreignId('created_recipient_id')->nullable()
                ->constrained('recipients')->nullOnDelete();

            $table->index(['import_job_id', 'validation_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_rows');
    }
};
