<?php

namespace App\Modules\Campaigns\Models;

use App\Modules\Campaigns\Enums\CampaignSendMode;
use App\Modules\Campaigns\Enums\CampaignSmtpStrategy;
use App\Modules\DeliveryEngine\Models\SmtpAccount;
use App\Modules\Identity\Models\User;
use App\Modules\Recipients\Models\RecipientList;
use App\Modules\Recipients\Models\Segment;
use App\Modules\Templates\Models\Template;
use App\Modules\Tracking\Models\Message;
use App\Support\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * docs/15-campaign-management.md — Campaign Management module.
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property int|null $template_id
 * @property string|null $subject
 * @property string|null $html_body
 * @property int|null $recipient_list_id
 * @property int|null $segment_id
 * @property string $status
 * @property string $smtp_strategy
 * @property int|null $single_smtp_account_id
 * @property string $send_mode
 * @property \Illuminate\Support\Carbon|null $scheduled_at
 * @property string|null $recurrence_rule
 * @property bool $business_hours_only
 * @property string|null $business_hours_start
 * @property string|null $business_hours_end
 * @property array<int, string>|null $business_days
 * @property bool $approval_required
 * @property int|null $approved_by
 * @property \Illuminate\Support\Carbon|null $approved_at
 * @property bool $tracking_enabled
 * @property int|null $cloned_from_campaign_id
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $sent_at
 */
class Campaign extends Model
{
    use HasUuid;

    protected $fillable = [
        'name',
        'template_id',
        'subject',
        'html_body',
        'recipient_list_id',
        'segment_id',
        'status',
        'smtp_strategy',
        'single_smtp_account_id',
        'send_mode',
        'scheduled_at',
        'recurrence_rule',
        'business_hours_only',
        'business_hours_start',
        'business_hours_end',
        'business_days',
        'approval_required',
        'approved_by',
        'approved_at',
        'tracking_enabled',
        'cloned_from_campaign_id',
        'created_by',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'approved_at' => 'datetime',
            'sent_at' => 'datetime',
            'business_hours_only' => 'boolean',
            'approval_required' => 'boolean',
            'tracking_enabled' => 'boolean',
            'business_days' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<Template, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    /**
     * @return BelongsTo<RecipientList, $this>
     */
    public function recipientList(): BelongsTo
    {
        return $this->belongsTo(RecipientList::class);
    }

    /**
     * @return BelongsTo<Segment, $this>
     */
    public function segment(): BelongsTo
    {
        return $this->belongsTo(Segment::class);
    }

    /**
     * @return BelongsTo<SmtpAccount, $this>
     */
    public function singleSmtpAccount(): BelongsTo
    {
        return $this->belongsTo(SmtpAccount::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return BelongsTo<Campaign, $this>
     */
    public function clonedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'cloned_from_campaign_id');
    }

    /**
     * §15.8 "Recipients" tab — per-recipient `message` status for this
     * campaign (`messages.campaign_id`).
     *
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function smtpStrategyEnum(): CampaignSmtpStrategy
    {
        return CampaignSmtpStrategy::from($this->smtp_strategy);
    }

    public function sendModeEnum(): CampaignSendMode
    {
        return CampaignSendMode::from($this->send_mode);
    }
}
