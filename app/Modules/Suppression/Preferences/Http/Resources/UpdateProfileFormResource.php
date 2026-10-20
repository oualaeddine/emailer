<?php

namespace App\Modules\Suppression\Preferences\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Modules\Suppression\Preferences\Models\UpdateProfileForm */
class UpdateProfileFormResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'is_default' => $this->is_default,
            'fields' => $this->fields,
            'list_ids' => $this->list_ids,
            'content' => $this->content,
            'design' => $this->design,
            'created_at' => $this->created_at?->toIso8601ZuluString(),
            'updated_at' => $this->updated_at?->toIso8601ZuluString(),
        ];
    }
}
