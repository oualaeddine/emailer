<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Domain\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Modules\Identity\Http\Resources\RoleResource;
use App\Modules\Identity\Services\RoleService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * docs/29-api-specification.md §29.2 — GET /api/v1/roles.
 */
class RoleController extends Controller
{
    public function __construct(private readonly RoleService $roles)
    {
    }

    public function index(): AnonymousResourceCollection
    {
        Gate::authorize(PermissionName::UsersManage->value);

        return RoleResource::collection($this->roles->all());
    }
}
