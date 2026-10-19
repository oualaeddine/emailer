<?php

namespace App\Modules\Recipients\Lists\Http\Resources;

use App\Modules\Recipients\Http\Resources\SegmentResource;
use App\Modules\Recipients\Lists\Models\ListFolder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * docs/45 §45.5.6 `ContactList` — superset of the legacy recipient-list resource.
 *
 * @mixin \App\Modules\Recipients\Models\RecipientList
 */
class ContactListResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $folderUuid = $this->folder_id
            ? ListFolder::query()->whereKey($this->folder_id)->value('uuid')
            : null;

        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'description' => $this->description,
            'folder_id' => $folderUuid,
            'type' => $this->type,
            'contact_count' => (int) $this->contact_count,
            'blocklisted_count' => (int) $this->blocklisted_count,
            'last_import_at' => $this->last_import_at
                ? \Illuminate\Support\Carbon::parse($this->last_import_at)->toIso8601String()
                : null,
            'created_by' => $this->whenLoaded('createdBy', fn () => $this->createdBy
                ? ['id' => $this->createdBy->uuid, 'name' => $this->createdBy->name]
                : null),
            'segment' => new SegmentResource($this->whenLoaded('segment')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
