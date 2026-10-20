<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** docs/45 §45.8 Slot M — `unsubscribe_feedback` (survey reasons). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unsubscribe_feedback', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('recipient_id')->nullable()->constrained('recipients')->nullOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained('campaigns')->nullOnDelete();
            $table->foreignId('message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->string('reason', 255)->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unsubscribe_feedback');
    }
};
