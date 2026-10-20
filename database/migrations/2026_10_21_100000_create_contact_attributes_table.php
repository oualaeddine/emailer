<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** docs/45 §45.8 Slot D — WP-50 `contact_attributes`. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('contact_attributes')) {
            return;
        }

        Schema::create('contact_attributes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 50)->unique();
            $table->string('label', 100)->nullable();
            $table->string('type', 32);
            $table->json('options')->nullable();
            $table->boolean('is_system')->default(false);
            $table->string('storage', 16)->default('custom_fields');
            $table->string('column', 64)->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_attributes');
    }
};
