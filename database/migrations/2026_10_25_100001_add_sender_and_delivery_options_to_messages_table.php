<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * next-sender — non-campaign messages (transactional API, automations) keep
 * the sender and the delivery options they were created with:
 * `delivery_options` = {reply_to, cc[], bcc[], headers{}, attachments[{disk, path, name, mime, size}]}.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table): void {
            if (! Schema::hasColumn('messages', 'sender_id')) {
                $table->foreignId('sender_id')->nullable()->constrained('senders')->nullOnDelete();
            }
            if (! Schema::hasColumn('messages', 'delivery_options')) {
                $table->json('delivery_options')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table): void {
            if (Schema::hasColumn('messages', 'sender_id')) {
                $table->dropForeign(['sender_id']);
                $table->dropColumn('sender_id');
            }
            if (Schema::hasColumn('messages', 'delivery_options')) {
                $table->dropColumn('delivery_options');
            }
        });
    }
};
