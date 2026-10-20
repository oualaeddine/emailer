<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** docs/45 §45.8 Slot Q — `form_submissions`. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_submissions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('form_id')->constrained('forms')->cascadeOnDelete();
            $table->foreignId('recipient_id')->nullable()->constrained('recipients')->nullOnDelete();
            $table->string('email', 254)->index();
            $table->string('status', 32)->index();
            $table->json('data');
            $table->char('confirmation_token_hash', 64)->nullable()->unique();
            $table->char('ip_hash', 64)->nullable();
            $table->char('ip_country', 2)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamp('submitted_at');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_submissions');
    }
};
