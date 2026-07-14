<?php

namespace App\Modules\Identity\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Identity\Models\User
 *
 * docs/04-database-design.md §4.1 — internal id never leaked; `uuid` is
 * serialized as `id`. `password`/`remember_token` are already hidden on
 * the model itself.
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'email' => $this->email,
            'avatar_path' => $this->avatar_path,
            'is_active' => $this->is_active,
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'role' => new RoleResource($this->whenLoaded('role')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
