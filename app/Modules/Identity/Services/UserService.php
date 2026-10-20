<?php

namespace App\Modules\Identity\Services;

use App\Domain\Events\UserCreated;
use App\Domain\Events\UserDeactivated;
use App\Domain\Events\UserPasswordChanged;
use App\Domain\Events\UserRoleChanged;
use App\Modules\Identity\DataTransferObjects\CreateUserData;
use App\Modules\Identity\DataTransferObjects\UpdateUserData;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * docs/29-api-specification.md §29.2 — Identity & Auth: /api/v1/users.
 * docs/26-rbac.md §26.3 — every action here is gated by `users.manage`
 * at the controller/policy layer, not repeated here.
 */
class UserService
{
    public function paginate(int $perPage = 25): LengthAwarePaginator
    {
        return User::query()
            ->with('role')
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function findByUuid(string $uuid): User
    {
        return User::query()->with('role')->where('uuid', $uuid)->firstOrFail();
    }

    public function create(CreateUserData $data, User $createdBy): User
    {
        return DB::transaction(function () use ($data, $createdBy): User {
            $user = User::query()->create([
                'name' => $data->name,
                'email' => $data->email,
                'password' => Hash::make($data->password),
                'role_id' => $data->roleId,
                'is_active' => true,
            ]);

            UserCreated::dispatch($user, $createdBy);

            return $user;
        });
    }

    public function update(User $user, UpdateUserData $data, User $updatedBy): User
    {
        return DB::transaction(function () use ($user, $data, $updatedBy): User {
            $oldRole = $user->role;
            $wasActive = $user->is_active;

            $user->fill(array_filter([
                'name' => $data->name,
                'role_id' => $data->roleId,
                'is_active' => $data->isActive,
            ], fn ($value) => $value !== null));

            if ($data->password !== null) {
                // 'hashed' cast on the model hashes it.
                $user->password = $data->password;
            }

            $user->save();

            if ($data->password !== null) {
                UserPasswordChanged::dispatch($user, $updatedBy, true);
                // An administrator reset must cut off whoever knew the old one.
                $user->tokens()->delete();
                $this->forgetOtherSessions($user);
            }

            if ($data->roleId !== null && $oldRole !== null && $oldRole->id !== $data->roleId) {
                $newRole = Role::query()->findOrFail($data->roleId);
                UserRoleChanged::dispatch($user, $oldRole, $newRole, $updatedBy);
            }

            if ($data->isActive === false && $wasActive === true) {
                UserDeactivated::dispatch($user, $updatedBy);
                // docs/45 §45.7.6 — a deactivated user's API keys are revoked.
                $user->tokens()->delete();
            }

            return $user->fresh('role');
        });
    }

    /**
     * Self-service change. The caller has already proven the current password.
     * Other sessions and API tokens are dropped; the current session survives.
     */
    public function changePassword(User $user, string $newPassword, ?string $keepSessionId = null): void
    {
        $user->forceFill(['password' => $newPassword])->save();

        UserPasswordChanged::dispatch($user, $user, false);

        $user->tokens()->delete();
        $this->forgetOtherSessions($user, $keepSessionId);
    }

    /**
     * Cheap session invalidation: only possible with the `database` session
     * driver (rows carry user_id). Other drivers keep their sessions alive.
     */
    private function forgetOtherSessions(User $user, ?string $keepSessionId = null): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        $query = DB::connection(config('session.connection'))
            ->table(config('session.table', 'sessions'))
            ->where('user_id', $user->getKey());

        if ($keepSessionId !== null) {
            $query->where('id', '!=', $keepSessionId);
        }

        $query->delete();
    }
}
