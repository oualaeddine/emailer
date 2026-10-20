<?php

namespace App\Modules\Campaigns\Models;

use App\Modules\Recipients\Models\Recipient;
use App\Modules\Tracking\Models\Message;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * docs/45-brevo-api-contracts.md §45.8 Slot B — materialised audience row
 * (docs/43 §43.6.1), iterated by `DispatchCampaignJob`.
 *
 * @property int $id
 * @property int $campaign_id
 * @property int $recipient_id
 * @property int|null $variant_id
 * @property int|null $message_id
 * @property string $status
 * @property string|null $skip_reason
 * @property Carbon|null $scheduled_for
 * @property Carbon|null $materialised_at
 */
class CampaignRecipient extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'campaign_id', 'recipient_id', 'variant_id', 'message_id', 'status', 'skip_reason',
        'scheduled_for', 'materialised_at',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_for' => 'datetime',
            'materialised_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * @return BelongsTo<Recipient, $this>
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(Recipient::class);
    }

    /**
     * @return BelongsTo<Message, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}
