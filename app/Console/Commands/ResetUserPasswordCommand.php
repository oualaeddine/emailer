<?php

namespace App\Console\Commands;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\UserService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Access recovery for a locked-out administrator who lost their password.
 * Sets a random strong password, printed once. Audited as `user.password_reset`
 * with no actor (system); the password itself is never logged.
 */
class ResetUserPasswordCommand extends Command
{
    protected $signature = 'users:reset-password {email : Adresse e-mail du compte} {--force : Ne pas demander de confirmation}';

    protected $description = 'Définit un mot de passe aléatoire pour un utilisateur et l’affiche une seule fois (récupération d’accès)';

    public function handle(UserService $users): int
    {
        $email = trim((string) $this->argument('email'));
        $user = User::query()->whereRaw('LOWER(email) = ?', [Str::lower($email)])->first();

        if ($user === null) {
            $this->error("Aucun utilisateur ne correspond à l’adresse « {$email} ».");

            return self::FAILURE;
        }

        if (! $this->option('force')
            && ! $this->confirm("Remplacer le mot de passe de {$user->name} <{$user->email}> par un mot de passe aléatoire ? Ses sessions et clés API seront révoquées.")) {
            $this->warn('Opération annulée.');

            return self::FAILURE;
        }

        $password = $users->resetPasswordToRandom($user);

        $this->info("Mot de passe réinitialisé pour {$user->email}. Ses sessions et clés API ont été révoquées.");
        $this->line('Nouveau mot de passe (affiché une seule fois) :');
        $this->line($password);

        return self::SUCCESS;
    }
}
