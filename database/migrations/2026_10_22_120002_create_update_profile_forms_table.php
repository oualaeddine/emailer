<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** docs/45 §45.8 Slot M — `update_profile_forms`. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('update_profile_forms', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 100);
            $table->boolean('is_default')->default(false)->index();
            $table->json('fields');
            $table->json('list_ids');
            $table->json('content');
            $table->json('design');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('update_profile_forms');
    }
};
