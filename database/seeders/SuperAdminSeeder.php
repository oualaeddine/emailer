<?php

namespace Database\Seeders;

use App\Domain\Enums\RoleName;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SuperAdminSeeder extends Seeder
{
    /**
     * Seed authoritative super administrator accounts.
     *
     * Outside production: the two well-known accounts with the password
     * "password" (development, e2e, demos), reset on every run.
     *
     * Production: a single account from config('app.super_admin')
     * (SUPER_ADMIN_EMAIL / SUPER_ADMIN_PASSWORD). Without a configured
     * password a random one is generated and printed once. An existing
     * account keeps its password, so re-running db:seed never resets it.
     */
    public function run(): void
    {
        $adminRole = Role::query()->where('name', RoleName::Administrator->value)->first();

        if (! $adminRole) {
            $this->call(RolePermissionSeeder::class);
            $adminRole = Role::query()->where('name', RoleName::Administrator->value)->firstOrFail();
        }

        if (app()->environment('production')) {
            $this->seedProductionAdmin($adminRole);

            return;
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

    private function seedProductionAdmin(Role $adminRole): void
    {
        $config = config('app.super_admin');
        $email = strtolower((string) ($config['email'] ?? 'admin@pagejaunes.dz'));

        if (User::query()->where('email', $email)->exists()) {
            $this->command?->info("Administrateur {$email} déjà présent : mot de passe inchangé.");

            return;
        }

        $password = (string) ($config['password'] ?? '');
        $generated = $password === '';
        if ($generated) {
            $password = Str::password(20);
        }

        (new User)->forceFill([
            'name' => $config['name'] ?? 'Super Administrateur',
            'email' => $email,
            'password' => Hash::make($password),
            'role_id' => $adminRole->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ])->save();

        if ($generated) {
            $this->command?->warn("Administrateur {$email} créé. Mot de passe (affiché une seule fois) : {$password}");
        } else {
            $this->command?->info("Administrateur {$email} créé avec SUPER_ADMIN_PASSWORD.");
        }
    }
}
