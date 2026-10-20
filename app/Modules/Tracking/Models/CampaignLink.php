<?php

namespace App\Modules\Tracking\Models;

use App\Modules\Campaigns\Models\Campaign;
use App\Support\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * docs/45-brevo-api-contracts.md §45.8 Slot L — per-link click counters of a
 * campaign, upserted by the click tracker (docs/43 §43.6.3).
 * `unique_clicks` counts distinct recipients, `total_clicks` every click.
 *
 * @property int $id
 * @property string $uuid
 * @property int $campaign_id
 * @property string $link_id
 * @property string $url
 * @property string|null $label
 * @property int $unique_clicks
 * @property int $total_clicks
 */
class CampaignLink extends Model
{
    use HasUuid;

    protected $fillable = [
        'campaign_id', 'link_id', 'url', 'url_hash', 'label', 'unique_clicks', 'total_clicks', 'last_clicked_at',
    ];

    protected function casts(): array
    {
        return [
            'unique_clicks' => 'integer',
            'total_clicks' => 'integer',
            'last_clicked_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Stable link id shared with the rendering pipeline's `data-link-id`
     * (WP-63): first 16 hex chars of sha1(original URL).
     */
    public static function linkIdFor(string $url): string
    {
        return substr(sha1($url), 0, 16);
    }

    /**
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
