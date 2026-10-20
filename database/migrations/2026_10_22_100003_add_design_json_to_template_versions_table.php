<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Template versions also snapshot the editor design so a restore brings back
 * the drag-and-drop / MJML model, not only the rendered HTML.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('template_versions', function (Blueprint $table): void {
            $table->json('design_json')->nullable();
            $table->string('editor_type', 32)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('template_versions', function (Blueprint $table): void {
            $table->dropColumn(['design_json', 'editor_type']);
        });
    }
};
