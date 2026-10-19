<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * docs/45-brevo-api-contracts.md §45.8 Slot F — folder, cached counters and
 * pivot `source` (WP-53). `description` already exists; the pivot already
 * has `added_at`. Counters are backfilled from the pivot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recipient_lists', function (Blueprint $table): void {
            $table->foreignId('folder_id')->nullable()->after('type')->constrained('list_folders')->nullOnDelete();
            $table->unsignedInteger('contact_count')->default(0)->after('folder_id');
            $table->unsignedInteger('blocklisted_count')->default(0)->after('contact_count');
            $table->timestamp('last_import_at')->nullable()->after('blocklisted_count');
        });

        Schema::table('recipient_list_recipient', function (Blueprint $table): void {
            $table->string('source', 32)->nullable();
        });

        DB::table('recipient_lists')->orderBy('id')->each(function (object $list): void {
            $base = DB::table('recipient_list_recipient as p')
                ->join('recipients as r', 'r.id', '=', 'p.recipient_id')
                ->where('p.recipient_list_id', $list->id)
                ->whereNull('r.deleted_at');

            DB::table('recipient_lists')->where('id', $list->id)->update([
                'contact_count' => (clone $base)->count(),
                'blocklisted_count' => (clone $base)->where('r.status', 'suppressed')->count(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('recipient_list_recipient', function (Blueprint $table): void {
            $table->dropColumn('source');
        });

        Schema::table('recipient_lists', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('folder_id');
            $table->dropColumn(['contact_count', 'blocklisted_count', 'last_import_at']);
        });
    }
};
