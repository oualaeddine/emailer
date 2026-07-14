<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/04-database-design.md §4.3 — `drafts.template_id` FK, deferred
 * until now since `templates` did not exist during the Composer module
 * (docs/34-roadmap.md build order: Composer before Templates).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drafts', function (Blueprint $table): void {
            $table->foreign('template_id', 'drafts_template_id_foreign')
                ->references('id')->on('templates')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('drafts', function (Blueprint $table): void {
            $table->dropForeign('drafts_template_id_foreign');
        });
    }
};
