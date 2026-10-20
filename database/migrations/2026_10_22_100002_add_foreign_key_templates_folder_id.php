<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/45-brevo-api-contracts.md §45.8 Slot K — `templates.folder_id` →
 * `template_folders.id` (nullOnDelete: deleting a folder moves its templates
 * to "no folder"). The column itself was added by slot C.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('templates', function (Blueprint $table): void {
            $table->foreign('folder_id')->references('id')->on('template_folders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table): void {
            $table->dropForeign(['folder_id']);
        });
    }
};
