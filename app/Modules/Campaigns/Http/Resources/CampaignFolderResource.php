<?php

namespace App\Modules\Campaigns\Http\Resources;

use App\Modules\Campaigns\Models\CampaignFolder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * docs/45-brevo-api-contracts.md §45.3 `Folder` (kind `campaign`).
 *
 * @mixin CampaignFolder
 */
class CampaignFolderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'kind' => 'campaign',
            'items_count' => (int) ($this->campaigns_count ?? $this->campaigns()->count()),
            'created_at' => CampaignResource::iso($this->created_at),
            'updated_at' => CampaignResource::iso($this->updated_at),
        ];
    }
}
