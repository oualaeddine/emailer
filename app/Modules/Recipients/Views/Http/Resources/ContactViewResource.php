<?php

namespace App\Modules\Recipients\Views\Http\Resources;

use App\Modules\Recipients\Views\Models\ContactView;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ContactView|array<string, mixed>
 */
class ContactViewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if (is_array($this->resource)) {
            return $this->resource;
        }

        /** @var ContactView $v */
        $v = $this->resource;

        return [
            'id' => $v->uuid,
            'name' => $v->name,
            'is_system' => false,
            'is_shared' => $v->is_shared,
            'filters' => $v->filters,
            'list_id' => $v->list?->uuid,
            'segment_id' => $v->segment?->uuid,
            'columns' => $v->columns ?? [],
            'sort' => $v->sort,
            'owner' => $v->owner !== null ? ['id' => $v->owner->uuid, 'name' => $v->owner->name] : null,
            'created_at' => $v->created_at?->toIso8601String(),
            'updated_at' => $v->updated_at?->toIso8601String(),
        ];
    }
}
