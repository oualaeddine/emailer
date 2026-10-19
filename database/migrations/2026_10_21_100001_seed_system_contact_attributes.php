<?php

use Database\Seeders\ContactAttributeSeeder;
use Illuminate\Database\Migrations\Migration;

/** docs/45 §45.8 Slot D — data migration: seeds the system attributes (idempotent). */
return new class extends Migration
{
    public function up(): void
    {
        (new ContactAttributeSeeder)->run();
    }

    public function down(): void
    {
        // System attributes are removed with the table.
    }
};
