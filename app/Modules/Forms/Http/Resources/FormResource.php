<?php

namespace App\Modules\Forms\Http\Resources;

use App\Modules\Forms\Services\FormEmbedService;
use App\Modules\Forms\Services\FormService;
use App\Modules\Recipients\Models\RecipientList;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

/**
 * docs/45 §45.7.4 `SubscriptionForm`.
 *
 * @mixin \App\Modules\Forms\Models\Form
 */
class FormResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $settings = array_merge(FormService::defaultSettings(), $this->settings ?? []);
        $settings['doi_template_id'] = $this->doi_template_id
            ? DB::table('templates')->where('id', $this->doi_template_id)->value('uuid')
            : null;
        $lists = RecipientList::query()->whereIn('uuid', $this->list_ids ?? [])->get(['uuid', 'name']);

        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'status' => $this->status,
            'list_ids' => array_values($this->list_ids ?? []),
            'lists' => $lists->map(fn ($l) => ['id' => $l->uuid, 'name' => $l->name])->all(),
            'fields' => app(FormEmbedService::class)->fields($this->resource),
            'gdpr' => array_merge(FormService::defaultGdpr(), $this->gdpr ?? []),
            'design' => array_merge(FormService::defaultDesign(), $this->design ?? []),
            'settings' => $settings,
            'share' => app(FormService::class)->share($this->resource),
            'submissions_count' => (int) $this->submissions_count,
            'confirmed_count' => (int) $this->confirmed_count,
            'last_submission_at' => $this->last_submission_at?->toIso8601String(),
            'created_by' => $this->whenLoaded('createdBy', fn () => $this->createdBy
                ? ['id' => $this->createdBy->uuid, 'name' => $this->createdBy->name]
                : null),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
