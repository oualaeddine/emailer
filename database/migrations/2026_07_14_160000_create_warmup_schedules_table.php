<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/04-database-design.md §4.9 — Delivery Engine Module: `warmup_schedules`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warmup_schedules', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 150);
            $table->json('stages');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warmup_schedules');
    }
};
