<?php

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * docs/45-brevo-api-contracts.md §45.2 (WP-40) — the ten Brevo-parity permissions
 * reach existing installs only through the (idempotent) role/permission seeder.
 * Re-run it as a data migration so Gates resolve them without a manual
 * `db:seed` step (Wave 0 review finding #6).
 */
return new class extends Migration
{
    public function up(): void
    {
        (new RolePermissionSeeder)->run();
    }

    public function down(): void
    {
        // Permissions are reference data; nothing to undo.
    }
};
