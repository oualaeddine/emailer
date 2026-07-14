<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backfills the FK deferred by 2026_07_14_160003 (send_attempts.message_id
 * had no `messages` table to reference yet at Module 8 build time).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('send_attempts', function (Blueprint $table): void {
            $table->foreign('message_id', 'send_attempts_message_id_foreign')
                ->references('id')->on('messages')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('send_attempts', function (Blueprint $table): void {
            $table->dropForeign('send_attempts_message_id_foreign');
        });
    }
};
