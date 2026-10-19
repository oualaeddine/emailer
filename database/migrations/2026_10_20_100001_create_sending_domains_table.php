<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** docs/45 §45.8 Slot A — `sending_domains`. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sending_domains', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('domain', 253)->unique();
            $table->string('status', 32)->default('pending');
            $table->string('dkim_selector', 63);
            $table->text('dkim_private_key')->nullable();
            $table->text('dkim_public_key')->nullable();
            $table->json('records');
            $table->timestamp('last_checked_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sending_domains');
    }
};
