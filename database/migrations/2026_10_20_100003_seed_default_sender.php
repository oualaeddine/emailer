<?php

use Database\Seeders\DefaultSenderSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Data migration: production never runs `db:seed`, so create the default sender
 * from MAIL_FROM_ADDRESS here (idempotent; same logic as DefaultSenderSeeder).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Test databases are built with RefreshDatabase: do not leave a sender behind.
        if (app()->environment('testing')) {
            return;
        }

        $this->seed();
    }

    public function seed(): void
    {
        if (Schema::hasTable('senders')) {
            (new DefaultSenderSeeder)->run();
        }
    }

    public function down(): void
    {
        // Data migration: nothing to undo.
    }
};
