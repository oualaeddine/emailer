<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** docs/45 §45.8 Slot R — WP-91 `messages.automation_step_id` (e-mails sent by a send_email step). */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('messages', 'automation_step_id')) {
            return;
        }

        Schema::table('messages', function (Blueprint $table): void {
            $table->foreignId('automation_step_id')->nullable()->index()->constrained('automation_steps')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('messages', 'automation_step_id')) {
            return;
        }

        Schema::table('messages', function (Blueprint $table): void {
            $table->dropForeign(['automation_step_id']);
            $table->dropColumn('automation_step_id');
        });
    }
};
