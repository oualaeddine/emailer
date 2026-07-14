<?php

namespace App\Modules\DeliveryEngine\Services;

use App\Domain\Enums\SmtpHealthStatus;
use App\Modules\DeliveryEngine\Models\SmtpAccount;
use Illuminate\Database\Eloquent\Collection;

/**
 * docs/18-smtp-management.md — SMTP Management.
 */
class SmtpAccountService
{
    /**
     * @return Collection<int, SmtpAccount>
     */
    public function all(): Collection
    {
        return SmtpAccount::query()->orderBy('priority')->orderBy('name')->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): SmtpAccount
    {
        return SmtpAccount::query()->create([
            ...$data,
            'health_status' => SmtpHealthStatus::Healthy->value,
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(SmtpAccount $account, array $data): SmtpAccount
    {
        // A blank password field means "keep current" (docs/18-smtp-management.md
        // §18.2 — "write-only field ... edit requires re-entry or explicit
        // 'keep current' toggle").
        if (array_key_exists('password_encrypted', $data) && $data['password_encrypted'] === '') {
            unset($data['password_encrypted']);
        }

        $account->update($data);

        return $account->fresh();
    }

    public function deactivate(SmtpAccount $account): SmtpAccount
    {
        $account->update(['is_active' => false]);

        return $account;
    }

    /**
     * docs/18-smtp-management.md §18.8 — hard delete blocked if referenced
     * by `send_attempts`; "Deactivate instead" is surfaced by the caller.
     */
    public function canBeDeleted(SmtpAccount $account): bool
    {
        return $account->sendAttempts()->doesntExist();
    }
}
