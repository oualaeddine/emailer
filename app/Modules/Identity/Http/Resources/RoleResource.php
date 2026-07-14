<?php

namespace App\Modules\Identity\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Identity\Models\Role
 *
 * `roles` has no `uuid` column (docs/04-database-design.md §4.2 — unlike
 * `users`, roles are a fixed reference list, not an externally-obfuscated
 * entity) — `id` is the value `users.role_id` actually stores, so it is
 * exposed directly rather than through a uuid indirection.
 */
class RoleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'permissions' => PermissionResource::collection($this->whenLoaded('permissions')),
        ];
    }
}
