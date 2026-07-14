<?php

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Models\Role;
use Illuminate\Database\Eloquent\Collection;

/**
 * docs/29-api-specification.md §29.2 — GET /api/v1/roles.
 */
class RoleService
{
    /**
     * @return Collection<int, Role>
     */
    public function all(): Collection
    {
        return Role::query()->with('permissions')->orderBy('name')->get();
    }
}
