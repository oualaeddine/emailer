<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * docs/45-brevo-api-contracts.md §45.8 Slot G — standalone segments (WP-54).
 *
 * `folder_id` references `list_folders` (kind = segment, created by WP-53):
 * indexed nullable column, no FK constraint here (parallel slot).
 * `recipient_list_id` becomes nullable and its FK switches to null-on-delete
 * so deleting a legacy list no longer deletes the (now standalone) segment.
 * Existing rows keep their legacy v1 rule tree (`rules_version` = 1); WP-54
 * converts it on read and stores it back as v2 on save.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('segments', function (Blueprint $table): void {
            $table->string('name', 150)->nullable()->after('uuid');
            $table->string('description', 500)->nullable()->after('name');
            $table->unsignedBigInteger('folder_id')->nullable()->after('description')->index();
            $table->unsignedInteger('contact_count')->nullable();
            $table->timestamp('count_computed_at')->nullable();
            $table->unsignedTinyInteger('rules_version')->default(2);
        });

        if (! Schema::hasColumn('segments', 'created_by')) {
            Schema::table('segments', function (Blueprint $table): void {
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            });
        }

        // Backfill: name from the owning list, legacy rule trees flagged v1.
        DB::table('segments')->orderBy('id')->chunkById(500, function ($rows): void {
            foreach ($rows as $row) {
                $list = $row->recipient_list_id !== null
                    ? DB::table('recipient_lists')->where('id', $row->recipient_list_id)->first(['name', 'created_by'])
                    : null;
                $rules = json_decode((string) $row->rules, true);

                DB::table('segments')->where('id', $row->id)->update([
                    'name' => mb_substr($list->name ?? 'Segment sans nom', 0, 150),
                    'created_by' => $list->created_by ?? null,
                    'contact_count' => $row->cached_count,
                    'count_computed_at' => $row->last_evaluated_at,
                    'rules_version' => is_array($rules) && isset($rules['version']) ? 2 : 1,
                ]);
            }
        });

        Schema::table('segments', function (Blueprint $table): void {
            $table->string('name', 150)->nullable(false)->change();
            $table->dropForeign(['recipient_list_id']);
        });

        Schema::table('segments', function (Blueprint $table): void {
            $table->unsignedBigInteger('recipient_list_id')->nullable()->change();
            $table->foreign('recipient_list_id')->references('id')->on('recipient_lists')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('segments', function (Blueprint $table): void {
            $table->dropForeign(['recipient_list_id']);
        });

        Schema::table('segments', function (Blueprint $table): void {
            $table->foreign('recipient_list_id')->references('id')->on('recipient_lists')->cascadeOnDelete();
            $table->dropIndex(['folder_id']);
            $table->dropColumn(['name', 'description', 'folder_id', 'contact_count', 'count_computed_at', 'rules_version']);
        });

        if (Schema::hasColumn('segments', 'created_by')) {
            Schema::table('segments', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('created_by');
            });
        }
    }
};
