<?php

namespace App\Console\Commands;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\UserService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Access recovery for a locked-out administrator (lost phone, no recovery
 * codes, nobody else can reset them from the Users page). Audited as
 * `user.two_factor_reset` with no actor (system).
 */
class ResetUserTwoFactorCommand extends Command
{
    protected $signature = 'users:reset-two-factor {email : Adresse e-mail du compte} {--force : Ne pas demander de confirmation}';

    protected $description = 'Réinitialise la double authentification d’un utilisateur (récupération d’accès)';

    public function handle(UserService $users): int
    {
        $email = trim((string) $this->argument('email'));
        $user = User::query()->whereRaw('LOWER(email) = ?', [Str::lower($email)])->first();

        if ($user === null) {
            $this->error("Aucun utilisateur ne correspond à l’adresse « {$email} ».");

            return self::FAILURE;
        }

        if (! $this->option('force')
            && ! $this->confirm("Réinitialiser la double authentification de {$user->name} <{$user->email}> ? Ses sessions et clés API seront révoquées.")) {
            $this->warn('Opération annulée.');

            return self::FAILURE;
        }

        $users->resetTwoFactor($user, null);

        $this->info("Double authentification réinitialisée pour {$user->email}. La personne devra la configurer de nouveau à sa prochaine connexion ; ses sessions et clés API ont été révoquées.");

        return self::SUCCESS;
    }
}
