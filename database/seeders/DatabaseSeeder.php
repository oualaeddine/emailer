<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Only RolePermissionSeeder (docs/26-rbac.md §26.6) and a single
     * bootstrap Administrator account are seeded here — this is an
     * operational bootstrap step (every fresh install needs one account
     * able to log in and create further users), not a documented data
     * requirement in its own right.
     */
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);
        $this->call(SettingsSeeder::class);

        $this->call(SuperAdminSeeder::class);
        $this->call(AlgeriaB2bDemoSeeder::class);
    }
}
