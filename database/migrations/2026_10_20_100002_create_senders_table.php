<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** docs/45 §45.8 Slot A — `senders`. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('senders', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 100);
            $table->string('email', 254)->unique();
            $table->foreignId('domain_id')->nullable()->constrained('sending_domains')->nullOnDelete();
            $table->string('reply_to', 254)->nullable();
            $table->boolean('is_default')->default(false)->index();
            $table->string('verification_method', 32)->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('verification_sent_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('senders');
    }
};
