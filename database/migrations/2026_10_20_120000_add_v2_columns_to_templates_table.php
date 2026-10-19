<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * docs/45-brevo-api-contracts.md §45.8 Slot C — WP-44 `templates` v2 columns.
 * Every column is guarded so the migration is re-runnable and tolerant of
 * columns added elsewhere. `folder_id` has no FK yet (slot K adds it).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('templates', function (Blueprint $table): void {
            if (! Schema::hasColumn('templates', 'editor_type')) {
                $table->string('editor_type', 32)->default('html');
            }
            if (! Schema::hasColumn('templates', 'design_json')) {
                $table->json('design_json')->nullable();
            }
            if (! Schema::hasColumn('templates', 'folder_id')) {
                $table->unsignedBigInteger('folder_id')->nullable();
            }
            if (! Schema::hasColumn('templates', 'scope')) {
                $table->string('scope', 32)->default('marketing')->index();
            }
            if (! Schema::hasColumn('templates', 'is_gallery')) {
                $table->boolean('is_gallery')->default(false)->index();
            }
            if (! Schema::hasColumn('templates', 'gallery_category')) {
                $table->string('gallery_category', 32)->nullable();
            }
            if (! Schema::hasColumn('templates', 'subject')) {
                $table->string('subject', 255)->nullable();
            }
            if (! Schema::hasColumn('templates', 'thumbnail_path')) {
                $table->string('thumbnail_path', 500)->nullable();
            }
            if (! Schema::hasColumn('templates', 'created_by')) {
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            }
        });

        // Existing templates were all authored as raw HTML.
        DB::table('templates')->whereNull('editor_type')->orWhere('editor_type', '')->update(['editor_type' => 'html']);
    }

    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table): void {
            foreach (['scope', 'is_gallery'] as $indexed) {
                if (Schema::hasColumn('templates', $indexed)) {
                    $table->dropIndex([$indexed]);
                }
            }
            $drop = array_values(array_filter(
                ['editor_type', 'design_json', 'folder_id', 'scope', 'is_gallery', 'gallery_category', 'subject'],
                fn (string $c): bool => Schema::hasColumn('templates', $c),
            ));
            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }
};
