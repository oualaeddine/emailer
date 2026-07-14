<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\DataTransferObjects\CreateUserData;
use App\Modules\Identity\DataTransferObjects\UpdateUserData;
use App\Modules\Identity\Http\Requests\StoreUserRequest;
use App\Modules\Identity\Http\Requests\UpdateUserRequest;
use App\Modules\Identity\Http\Resources\UserResource;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * docs/29-api-specification.md §29.2 — GET/POST /api/v1/users, PATCH /api/v1/users/{uuid}.
 * Every action is additionally gated by the `users.manage` Policy
 * (docs/26-rbac.md §26.3) via {@see \App\Modules\Identity\Policies\UserPolicy}.
 */
class UserController extends Controller
{
    public function __construct(private readonly UserService $users)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', User::class);

        return UserResource::collection($this->users->paginate());
    }

    public function store(StoreUserRequest $request): UserResource
    {
        $user = $this->users->create(
            CreateUserData::fromArray($request->validated()),
            $request->user(),
        );

        return new UserResource($user->load('role'));
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $this->authorize('update', $user);

        $updated = $this->users->update(
            $user,
            UpdateUserData::fromArray($request->validated()),
            $request->user(),
        );

        return new UserResource($updated);
    }
}
