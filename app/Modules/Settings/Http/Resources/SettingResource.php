<?php

namespace App\Modules\Settings\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * docs/29-api-specification.md §29.11 — "secret values never returned in GET".
 *
 * @mixin \App\Modules\Settings\Models\Setting
 */
class SettingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->key,
            'value' => $this->is_secret ? null : $this->value(),
            'value_type' => $this->value_type,
            'is_secret' => $this->is_secret,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
