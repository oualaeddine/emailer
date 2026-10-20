<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/45-brevo-api-contracts.md §45.8 Slot N — `saved_sections` (WP-61):
 * whole email sections saved from the EmailBuilder (*Enregistrés* tab),
 * shared by every user of the account.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_sections', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 100);
            $table->longText('mjml');
            $table->json('component');
            $table->string('thumbnail_path', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_sections');
    }
};
