<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/45 §45.8 Slot J — `users.column_preferences`, `recipients.created_by`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'column_preferences')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->json('column_preferences')->nullable();
            });
        }

        if (! Schema::hasColumn('recipients', 'created_by')) {
            Schema::table('recipients', function (Blueprint $table): void {
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('recipients', 'created_by')) {
            Schema::table('recipients', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('created_by');
            });
        }

        if (Schema::hasColumn('users', 'column_preferences')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('column_preferences');
            });
        }
    }
};
