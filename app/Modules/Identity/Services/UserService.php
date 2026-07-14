<?php

namespace App\Modules\Identity\Services;

use App\Domain\Events\UserCreated;
use App\Domain\Events\UserDeactivated;
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

            $user->save();

            if ($data->roleId !== null && $oldRole !== null && $oldRole->id !== $data->roleId) {
                $newRole = Role::query()->findOrFail($data->roleId);
                UserRoleChanged::dispatch($user, $oldRole, $newRole, $updatedBy);
            }

            if ($data->isActive === false && $wasActive === true) {
                UserDeactivated::dispatch($user, $updatedBy);
            }

            return $user->fresh('role');
        });
    }
}
