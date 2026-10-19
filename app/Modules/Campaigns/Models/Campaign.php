<?php

namespace App\Modules\Campaigns\Models;

use App\Modules\Campaigns\Enums\CampaignSendMode;
use App\Modules\Campaigns\Enums\CampaignSmtpStrategy;
use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\DeliveryEngine\Models\SmtpAccount;
use App\Modules\Identity\Models\User;
use App\Modules\Recipients\Models\RecipientList;
use App\Modules\Recipients\Models\Segment;
use App\Modules\Templates\Models\Template;
use App\Modules\Tracking\Models\Message;
use App\Support\Concerns\HasUuid;
use Database\Factories\CampaignFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * docs/15-campaign-management.md — Campaign Management module, extended by
 * docs/43-brevo-parity-plan.md §43.4.1–43.4.2 / docs/45 §45.8 Slot B (v2).
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $type
 * @property int|null $template_id
 * @property string|null $subject
 * @property string|null $preview_text
 * @property string|null $html_body
 * @property array<string, mixed>|null $design_json
 * @property string|null $editor_type
 * @property int|null $recipient_list_id
 * @property int|null $segment_id
 * @property string $status
 * @property string|null $suspended_reason
 * @property Carbon|null $archived_at
 * @property int|null $folder_id
 * @property array<int, string>|null $tags
 * @property int|null $sender_id
 * @property string $smtp_strategy
 * @property int|null $single_smtp_account_id
 * @property string $send_mode
 * @property Carbon|null $scheduled_at
 * @property string|null $timezone
 * @property int|null $batch_size
 * @property int|null $batch_interval_minutes
 * @property bool $send_time_optimized
 * @property bool $business_hours_only
 * @property array<int, string|int>|null $business_days
 * @property bool $approval_required
 * @property int|null $approved_by
 * @property bool $tracking_enabled
 * @property int|null $cloned_from_campaign_id
 * @property int|null $parent_campaign_id
 * @property int|null $created_by
 * @property Carbon|null $sent_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $test_sent_at
 */
class Campaign extends Model
{
    /** @use HasFactory<CampaignFactory> */
    use HasFactory, HasUuid;

    /**
     * docs/45 §45.8 Slot B stats cache counters (written by WP-70 rollups).
     */
    public const COUNTER_COLUMNS = [
        'recipients_count', 'sent_count', 'delivered_count', 'unique_opens', 'total_opens',
        'unique_clicks', 'total_clicks', 'unsubscribes', 'hard_bounces', 'soft_bounces',
        'complaints', 'proxy_opens', 'mirror_clicks',
    ];

    /**
     * Setup / design / sending settings copied by duplicate, clone and
     * send-to-new-contacts (never status, schedule, approval or stats).
     */
    public const COPYABLE_COLUMNS = [
        'type', 'template_id', 'subject', 'preview_text', 'html_body', 'editor_type', 'design_json',
        'thumbnail_path', 'recipient_list_id', 'segment_id', 'sender_id', 'reply_to', 'to_field',
        'folder_id', 'tags', 'timezone', 'utm_enabled', 'utm_source', 'utm_medium', 'utm_campaign',
        'utm_content', 'utm_term', 'utm_id', 'mirror_link_enabled', 'inline_images', 'attachment_id',
        'email_expiration_duration', 'email_expiration_unit', 'unsubscribe_page_id',
        'update_profile_form_id', 'smtp_strategy', 'single_smtp_account_id', 'send_mode',
        'business_hours_only', 'business_hours_start', 'business_hours_end', 'business_days',
        'approval_required', 'tracking_enabled', 'ab_test_enabled', 'ab_test_config',
    ];

    protected $fillable = [
        'name',
        'type',
        'template_id',
        'subject',
        'preview_text',
        'html_body',
        'editor_type',
        'design_json',
        'thumbnail_path',
        'recipient_list_id',
        'segment_id',
        'status',
        'suspended_reason',
        'archived_at',
        'folder_id',
        'tags',
        'sender_id',
        'reply_to',
        'to_field',
        'timezone',
        'utm_enabled',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_content',
        'utm_term',
        'utm_id',
        'mirror_link_enabled',
        'inline_images',
        'attachment_id',
        'email_expiration_duration',
        'email_expiration_unit',
        'unsubscribe_page_id',
        'update_profile_form_id',
        'smtp_strategy',
        'single_smtp_account_id',
        'send_mode',
        'scheduled_at',
        'recurrence_rule',
        'send_time_optimized',
        'batch_size',
        'batch_interval_minutes',
        'business_hours_only',
        'business_hours_start',
        'business_hours_end',
        'business_days',
        'approval_required',
        'approved_by',
        'approved_at',
        'tracking_enabled',
        'ab_test_enabled',
        'ab_test_config',
        'winner_variant_id',
        'test_sent_at',
        'cloned_from_campaign_id',
        'parent_campaign_id',
        'created_by',
        'sent_at',
        'completed_at',
        'recipients_count',
        'sent_count',
        'delivered_count',
        'unique_opens',
        'total_opens',
        'unique_clicks',
        'total_clicks',
        'unsubscribes',
        'hard_bounces',
        'soft_bounces',
        'complaints',
        'proxy_opens',
        'mirror_clicks',
        'stats_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'approved_at' => 'datetime',
            'sent_at' => 'datetime',
            'completed_at' => 'datetime',
            'archived_at' => 'datetime',
            'test_sent_at' => 'datetime',
            'stats_updated_at' => 'datetime',
            'business_hours_only' => 'boolean',
            'approval_required' => 'boolean',
            'tracking_enabled' => 'boolean',
            'utm_enabled' => 'boolean',
            'mirror_link_enabled' => 'boolean',
            'inline_images' => 'boolean',
            'send_time_optimized' => 'boolean',
            'ab_test_enabled' => 'boolean',
            'business_days' => 'array',
            'tags' => 'array',
            'design_json' => 'array',
            'ab_test_config' => 'array',
            'batch_size' => 'integer',
            'batch_interval_minutes' => 'integer',
            'email_expiration_duration' => 'integer',
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
     * Legacy single-list target (docs/45 §45.10) — superseded by {@see audiences()}.
     *
     * @return BelongsTo<RecipientList, $this>
     */
    public function recipientList(): BelongsTo
    {
        return $this->belongsTo(RecipientList::class);
    }

    /**
     * Legacy single-segment target (docs/45 §45.10) — superseded by {@see audiences()}.
     *
     * @return BelongsTo<Segment, $this>
     */
    public function segment(): BelongsTo
    {
        return $this->belongsTo(Segment::class);
    }

    /**
     * @return HasMany<CampaignAudience, $this>
     */
    public function audiences(): HasMany
    {
        return $this->hasMany(CampaignAudience::class);
    }

    /**
     * @return HasMany<CampaignRecipient, $this>
     */
    public function campaignRecipients(): HasMany
    {
        return $this->hasMany(CampaignRecipient::class);
    }

    /**
     * @return BelongsTo<CampaignFolder, $this>
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(CampaignFolder::class, 'folder_id');
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
     * @return BelongsTo<Campaign, $this>
     */
    public function parentCampaign(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_campaign_id');
    }

    /**
     * §15.8 "Recipients" tab — per-recipient `message` status for this
     * campaign (`messages.campaign_id`). Includes test sends; use
     * {@see deliveryMessages()} to exclude them.
     *
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Campaign messages excluding test sends (`messages.is_test = false`).
     *
     * @return HasMany<Message, $this>
     */
    public function deliveryMessages(): HasMany
    {
        return $this->hasMany(Message::class)->where('is_test', false);
    }

    public function statusEnum(): CampaignStatus
    {
        return CampaignStatus::from($this->status);
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function smtpStrategyEnum(): CampaignSmtpStrategy
    {
        return CampaignSmtpStrategy::from($this->smtp_strategy);
    }

    public function sendModeEnum(): CampaignSendMode
    {
        return CampaignSendMode::from($this->send_mode);
    }

    protected static function newFactory(): CampaignFactory
    {
        return CampaignFactory::new();
    }
}
