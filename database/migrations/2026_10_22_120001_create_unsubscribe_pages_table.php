<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** docs/45 §45.8 Slot M — `unsubscribe_pages`. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unsubscribe_pages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 100);
            $table->boolean('is_default')->default(false)->index();
            $table->json('content');
            $table->json('design');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unsubscribe_pages');
    }
};
