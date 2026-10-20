<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/45 §45.8 Slot J — WP-57 `contact_views`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('contact_views')) {
            return;
        }

        Schema::create('contact_views', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 100);
            $table->json('filters')->nullable();
            $table->foreignId('list_id')->nullable()->constrained('recipient_lists')->nullOnDelete();
            $table->foreignId('segment_id')->nullable()->constrained('segments')->nullOnDelete();
            $table->json('columns');
            $table->string('sort', 100)->nullable();
            $table->boolean('is_shared')->default(false);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_views');
    }
};
