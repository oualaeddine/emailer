<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/45-brevo-api-contracts.md §45.8 Slot H — WP-55 import wizard columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_jobs', function (Blueprint $table): void {
            $table->json('mapping')->nullable();
            $table->json('list_ids')->nullable();
            $table->json('new_list')->nullable();
            $table->boolean('update_existing')->default(true);
            $table->boolean('empty_values_overwrite')->default(false);
            $table->boolean('mark_blocklisted')->default(false);
            $table->boolean('mark_opt_in')->default(false);
            $table->json('summary')->nullable();
            $table->foreignId('created_list_id')->nullable()->constrained('recipient_lists')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('import_jobs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('created_list_id');
            $table->dropColumn([
                'mapping', 'list_ids', 'new_list', 'update_existing', 'empty_values_overwrite',
                'mark_blocklisted', 'mark_opt_in', 'summary',
            ]);
        });
    }
};
