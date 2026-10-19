<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/45-brevo-api-contracts.md §45.8 Slot F — `list_folders` (WP-53).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('list_folders', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 100);
            $table->string('kind', 16)->default('list');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['kind', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('list_folders');
    }
};
