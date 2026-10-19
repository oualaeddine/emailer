<?php

namespace App\Modules\Campaigns\Models;

use App\Modules\Campaigns\Enums\AudienceMode;
use App\Modules\Campaigns\Enums\AudienceTargetType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * docs/45-brevo-api-contracts.md §45.8 Slot B — one include/exclude target
 * (list or segment) of a campaign. `target_id` is polymorphic (no FK).
 *
 * @property int $id
 * @property int $campaign_id
 * @property string $target_type
 * @property int $target_id
 * @property string $mode
 */
class CampaignAudience extends Model
{
    protected $fillable = ['campaign_id', 'target_type', 'target_id', 'mode'];

    protected function casts(): array
    {
        return ['target_id' => 'integer'];
    }

    /**
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function targetTypeEnum(): AudienceTargetType
    {
        return AudienceTargetType::from($this->target_type);
    }

    public function modeEnum(): AudienceMode
    {
        return AudienceMode::from($this->mode);
    }
}
