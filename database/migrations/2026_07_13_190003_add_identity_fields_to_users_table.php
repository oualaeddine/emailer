<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/04-database-design.md §4.2 — Identity Module: `users` addenda.
 * The base Laravel migration already provides id, name, email
 * (unique), password, email_verified_at, remember_token, timestamps.
 * This migration adds the fields documented for `users` that are not
 * part of the Laravel default skeleton.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->uuid('uuid')->unique()->after('id');
            $table->foreignId('role_id')->after('email')->constrained('roles')->restrictOnDelete();
            $table->string('avatar_path')->nullable()->after('role_id');
            $table->boolean('is_active')->default(true)->after('avatar_path');
            $table->timestampTz('last_login_at')->nullable()->after('is_active');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropSoftDeletes();
            $table->dropColumn(['uuid', 'avatar_path', 'is_active', 'last_login_at']);
            $table->dropConstrainedForeignId('role_id');
        });
    }
};
