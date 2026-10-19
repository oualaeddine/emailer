<?php

namespace App\Modules\Recipients\Segments\Http\Resources;

use App\Modules\Recipients\Models\Segment;
use App\Modules\Recipients\Segments\Services\SegmentService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * docs/45-brevo-api-contracts.md §45.5.2 — `Segment`. Legacy keys of the
 * previous SegmentResource (`last_evaluated_at`, `cached_count`) are kept
 * (§45.1 "v2 fields are added").
 *
 * @mixin Segment
 */
class SegmentResource extends JsonResource
{
    /**
     * Preloads `used_in` and folder uuids for a page (avoids N+1).
     *
     * @param  iterable<Segment>  $segments
     */
    public static function prime(iterable $segments): void
    {
        $collection = new Collection(is_array($segments) ? $segments : iterator_to_array($segments, false));
        if ($collection->isEmpty()) {
            return;
        }

        $service = app(SegmentService::class);
        $usedIn = $service->usedIn($collection);
        $folders = $service->folderUuids($collection);
        $collection->loadMissing(['createdBy', 'recipientList' => fn ($q) => $q->withTrashed()]);

        foreach ($collection as $segment) {
            $segment->setRelation('usedInCounts', $usedIn[$segment->id] ?? ['campaigns' => 0, 'automations' => 0]);
            $segment->setRelation('folderUuid', $segment->folder_id !== null ? ($folders[$segment->folder_id] ?? null) : null);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if (! $this->resource->relationLoaded('usedInCounts')) {
            self::prime([$this->resource]);
        }

        $creator = $this->createdBy;

        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'description' => $this->description,
            'folder_id' => $this->resource->getRelation('folderUuid'),
            'rules' => app(SegmentService::class)->rulesOf($this->resource),
            'contact_count' => $this->contact_count,
            'count_computed_at' => $this->count_computed_at?->toIso8601ZuluString(),
            'recipient_list_id' => $this->recipientList?->uuid,
            'used_in' => $this->resource->getRelation('usedInCounts'),
            'created_by' => $creator !== null ? ['id' => $creator->uuid, 'name' => $creator->name] : null,
            'created_at' => $this->created_at?->toIso8601ZuluString(),
            'updated_at' => $this->updated_at?->toIso8601ZuluString(),
            'last_evaluated_at' => $this->last_evaluated_at?->toIso8601String(),
            'cached_count' => $this->cached_count,
        ];
    }
}
