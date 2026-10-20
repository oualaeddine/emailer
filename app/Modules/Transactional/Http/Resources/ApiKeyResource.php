<?php

namespace App\Modules\Transactional\Http\Resources;

use App\Modules\Transactional\Services\ApiKeyService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Laravel\Sanctum\PersonalAccessToken;

/** @mixin PersonalAccessToken */
class ApiKeyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $meta = ApiKeyService::meta($this->resource);
        $creator = $this->tokenable;

        return [
            'id' => $meta['id'] ?? null,
            'name' => $meta['label'] ?? '',
            'scopes' => array_values($this->abilities ?? []),
            'token_prefix' => $meta['prefix'] ?? '',
            'last_used_at' => $this->last_used_at?->toIso8601ZuluString(),
            'last_used_ip' => $meta['ip'] ?? null,
            'expires_at' => $this->expires_at?->toIso8601ZuluString(),
            'created_by' => $creator ? ['id' => $creator->uuid, 'name' => $creator->name] : null,
            'created_at' => $this->created_at?->toIso8601ZuluString(),
        ];
    }
}
