<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/04-database-design.md §4.12 — `suppression_entries`. Built as part
 * of the Delivery Engine (docs/17-delivery-engine.md §17.10, §17.3 —
 * Suppression Manager is consulted on every send) rather than deferred,
 * since `SendEmailJob` cannot safely operate without this gate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppression_entries', function (Blueprint $table): void {
            $table->id();
            $table->string('email')->unique();
            $table->string('reason', 30);
            $table->unsignedBigInteger('source_message_id')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppression_entries');
    }
};
