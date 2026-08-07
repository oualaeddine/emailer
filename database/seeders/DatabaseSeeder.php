<?php

namespace Database\Seeders;

use App\Domain\Enums\RoleName;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
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

        if (! User::query()->exists()) {
            User::factory()->create([
                'name' => 'Administrateur',
                'email' => 'admin@pagejaunes-mailer.local',
                'role_id' => Role::query()->where('name', RoleName::Administrator->value)->value('id'),
                'is_active' => true,
            ]);
        }

        $this->call(AlgeriaB2bDemoSeeder::class);
    }
}
