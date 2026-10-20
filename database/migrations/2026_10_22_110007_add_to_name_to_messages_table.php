<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/45-brevo-api-contracts.md §45.6.3 (Wave 3 review S7) — the To display
 * name rendered from the campaign `to_field` in the personalise phase (with the
 * merge contact of a test send), stored so the delivery phase sends exactly it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('messages', 'to_name')) {
            Schema::table('messages', function (Blueprint $table): void {
                $table->string('to_name', 255)->nullable()->after('subject');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('messages', 'to_name')) {
            Schema::table('messages', function (Blueprint $table): void {
                $table->dropColumn('to_name');
            });
        }
    }
};
