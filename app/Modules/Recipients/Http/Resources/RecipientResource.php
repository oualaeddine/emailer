<?php

namespace App\Modules\Recipients\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * docs/04-database-design.md §4.1 — internal id never leaked; `uuid`
 * serialized as `id`.
 *
 * @mixin \App\Modules\Recipients\Models\Recipient
 */
class RecipientResource extends JsonResource
{
    private const COUNTERS = ['messages_sent', 'delivered', 'opened', 'clicked', 'hard_bounces', 'soft_bounces', 'complaints', 'unsubscriptions'];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $raw = $this->resource->getAttribute('blocklisted_at');
        $blocklistedAt = $raw !== null ? Carbon::parse($raw) : null;
        $blocklisted = $blocklistedAt !== null || $this->status === 'suppressed';

        $counters = [];
        foreach (self::COUNTERS as $counter) {
            if (array_key_exists($counter, $this->resource->getAttributes())) {
                $counters[$counter] = (int) $this->resource->getAttribute($counter);
            }
        }

        return [
            'id' => $this->uuid,
            'email' => $this->email,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'company_name' => $this->company_name,
            'source' => $this->source,
            'status' => $this->status,
            'custom_fields' => $this->custom_fields,
            'last_contacted_at' => $this->last_contacted_at?->toIso8601String(),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'notes' => RecipientNoteResource::collection($this->whenLoaded('notes')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'blocklisted' => $blocklisted,
            'blocklisted_at' => $blocklistedAt?->toIso8601String(),
            'subscription_status' => $blocklisted ? 'blocklisted' : 'subscribed',
            'attributes' => (object) ($this->custom_fields ?? []),
            'pagejaunes_external_id' => $this->relationLoaded('pageJaunesCompany')
                ? $this->pageJaunesCompany?->pagejaunes_company_id
                : null,
            'lists' => $this->whenLoaded('recipientLists', fn () => $this->recipientLists
                ->map(fn ($l) => ['id' => $l->uuid, 'uuid' => $l->uuid, 'name' => $l->name])->all()),
            'counters' => $this->when($counters !== [], $counters),
        ];
    }
}
