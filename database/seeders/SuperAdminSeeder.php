<?php

namespace Database\Seeders;

use App\Domain\Enums\RoleName;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Seed authoritative super administrator accounts.
     * Guaranteed to exist, be active, verified, hold the Administrator role,
     * and have known login credentials.
     */
    public function run(): void
    {
        $adminRole = Role::query()->where('name', RoleName::Administrator->value)->first();

        if (! $adminRole) {
            $this->call(RolePermissionSeeder::class);
            $adminRole = Role::query()->where('name', RoleName::Administrator->value)->firstOrFail();
        }

        $superAdmins = [
            [
                'name' => 'Super Administrateur',
                'email' => 'admin@pagejaunes.dz',
                'password' => 'password',
            ],
            [
                'name' => 'Super Administrateur',
                'email' => 'admin@pagejaunes-mailer.local',
                'password' => 'password',
            ],
        ];

        foreach ($superAdmins as $adminData) {
            User::query()->updateOrCreate(
                ['email' => $adminData['email']],
                [
                    'name' => $adminData['name'],
                    'password' => Hash::make($adminData['password']),
                    'role_id' => $adminRole->id,
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            );
        }
    }
}
