<?php

namespace App\Modules\Composer\Services;

use App\Modules\Composer\Models\Signature;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * docs/04-database-design.md §4.3 — "one `is_default=true` per user
 * enforced in application service" (not a DB constraint).
 */
class SignatureService
{
    /**
     * @return Collection<int, Signature>
     */
    public function forUser(User $user): Collection
    {
        return Signature::query()->where('user_id', $user->id)->orderByDesc('is_default')->orderBy('name')->get();
    }

    public function create(User $user, string $name, string $htmlContent, bool $isDefault): Signature
    {
        return DB::transaction(function () use ($user, $name, $htmlContent, $isDefault): Signature {
            if ($isDefault) {
                $this->clearExistingDefault($user);
            }

            return Signature::query()->create([
                'user_id' => $user->id,
                'name' => $name,
                'html_content' => $htmlContent,
                'is_default' => $isDefault,
            ]);
        });
    }

    public function update(Signature $signature, string $name, string $htmlContent, bool $isDefault): Signature
    {
        return DB::transaction(function () use ($signature, $name, $htmlContent, $isDefault): Signature {
            if ($isDefault && ! $signature->is_default) {
                $this->clearExistingDefault($signature->user);
            }

            $signature->update(['name' => $name, 'html_content' => $htmlContent, 'is_default' => $isDefault]);

            return $signature;
        });
    }

    private function clearExistingDefault(User $user): void
    {
        Signature::query()->where('user_id', $user->id)->where('is_default', true)->update(['is_default' => false]);
    }
}
