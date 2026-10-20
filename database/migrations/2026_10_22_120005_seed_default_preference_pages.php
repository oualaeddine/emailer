<?php

use Database\Seeders\DefaultPreferencePagesSeeder;
use Illuminate\Database\Migrations\Migration;

/** docs/45 §45.8 Slot M — data migration: default pages + double opt-in template (idempotent). */
return new class extends Migration
{
    public function up(): void
    {
        (new DefaultPreferencePagesSeeder)->run();
    }

    public function down(): void
    {
        // Removed with the tables.
    }
};
