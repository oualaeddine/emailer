<?php

namespace App\Modules\Tracking\Models;

use App\Domain\Enums\MessageStatus;
use App\Modules\Composer\Models\Draft;
use App\Modules\DeliveryEngine\Models\SendAttempt;
use App\Modules\DeliveryEngine\Models\SmtpAccount;
use App\Modules\Recipients\Models\Recipient;
use App\Support\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * docs/04-database-design.md §4.10 — `messages`, the canonical record of
 * every individual email sent (transactional or campaign).
 *
 * @property int $id
 * @property string $uuid
 * @property int|null $campaign_id
 * @property int|null $draft_id
 * @property int $recipient_id
 * @property int|null $smtp_account_id
 * @property string $subject
 * @property string $html_body
 * @property string $status
 * @property int $open_count
 * @property int $click_count
 */
class Message extends Model
{
    use HasUuid;

    protected $fillable = [
        'campaign_id',
        'draft_id',
        'recipient_id',
        'smtp_account_id',
        'subject',
        'html_body',
        'status',
        'queued_at',
        'provider_message_id',
        'last_provider_response',
    ];

    protected function casts(): array
    {
        return [
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'opened_at' => 'datetime',
            'clicked_at' => 'datetime',
            'bounced_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<Recipient, $this>
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(Recipient::class);
    }

    /**
     * @return BelongsTo<Draft, $this>
     */
    public function draft(): BelongsTo
    {
        return $this->belongsTo(Draft::class);
    }

    /**
     * @return BelongsTo<SmtpAccount, $this>
     */
    public function smtpAccount(): BelongsTo
    {
        return $this->belongsTo(SmtpAccount::class);
    }

    /**
     * @return HasMany<SendAttempt, $this>
     */
    public function sendAttempts(): HasMany
    {
        return $this->hasMany(SendAttempt::class);
    }

    /**
     * Transitions status and stamps the corresponding "first reached at"
     * timestamp column exactly once (docs/04-database-design.md §4.10 —
     * "each set once first reached").
     */
    public function markStatus(MessageStatus $status): void
    {
        $this->status = $status->value;

        $column = match ($status) {
            MessageStatus::Accepted => 'sent_at',
            MessageStatus::Delivered => 'delivered_at',
            MessageStatus::Opened => 'opened_at',
            MessageStatus::Clicked => 'clicked_at',
            MessageStatus::SoftBounced, MessageStatus::HardBounced => 'bounced_at',
            MessageStatus::Failed => 'failed_at',
            default => null,
        };

        if ($column !== null && $this->{$column} === null) {
            $this->{$column} = now();
        }

        $this->save();
    }
}
