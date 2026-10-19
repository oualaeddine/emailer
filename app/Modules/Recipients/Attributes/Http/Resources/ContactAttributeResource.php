<?php

namespace App\Modules\Recipients\Attributes\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Modules\Recipients\Attributes\Models\ContactAttribute */
class ContactAttributeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'label' => $this->label ?: $this->name,
            'type' => $this->type,
            'options' => $this->isChoice() ? array_values($this->options ?? []) : [],
            'is_system' => $this->is_system,
            'storage' => $this->storage,
            'column' => $this->column,
            'merge_tag' => '{{ contact.'.$this->name.' }}',
            'usage_count' => $this->additional['usage'][$this->name] ?? null,
            'created_at' => $this->created_at?->toIso8601ZuluString(),
            'updated_at' => $this->updated_at?->toIso8601ZuluString(),
        ];
    }
}
