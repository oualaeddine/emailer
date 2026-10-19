<?php

namespace App\Modules\Campaigns\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * docs/45-brevo-api-contracts.md §45.4.5.
 *
 * @mixin \App\Modules\Campaigns\Services\Checklist\CampaignChecklist
 */
class CampaignChecklistResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->resource->toArray();
    }
}
