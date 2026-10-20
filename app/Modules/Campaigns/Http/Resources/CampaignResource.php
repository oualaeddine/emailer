<?php

namespace App\Modules\Campaigns\Http\Resources;

use App\Modules\Campaigns\Enums\CampaignSendMode;
use App\Modules\Campaigns\Enums\CampaignSmtpStrategy;
use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Services\CampaignService;
use App\Modules\Campaigns\Support\ExternalReferences;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * docs/45-brevo-api-contracts.md §45.4.2 `CampaignV2` (list item) — a
 * superset of the legacy docs/15 §15.8 resource (every legacy key kept).
 * Contract values win for the two keys whose spelling changed:
 * `smtp_strategy` (`rotation`|`single`) and `send_mode` (`now`|…).
 * Rates are computed from the stats cache counters (0 until WP-70 fills them).
 * `html_body` is only in the detail resource (list rows stay small).
 *
 * @mixin Campaign
 */
class CampaignResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = $this->statusEnum();
        $archived = $this->isArchived();

        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'type' => $this->type ?? 'classic',
            'status' => $this->status,
            'brevo_status' => $status->brevoStatus($archived)->value,
            'is_archived' => $archived,
            'archived_at' => self::iso($this->archived_at),
            'suspended_reason' => $this->suspended_reason,
            'folder_id' => $this->folder?->uuid,
            'tags' => $this->tags ?? [],

            'template_id' => $this->template?->uuid,
            'subject' => $this->subject,
            'preview_text' => $this->preview_text,
            'sender' => app(ExternalReferences::class)->sender($this->sender_id),
            'editor_type' => $this->editor_type,
            'thumbnail_url' => $this->thumbnail_path !== null ? Storage::disk('public')->url($this->thumbnail_path) : null,

            'recipient_list_id' => $this->recipientList?->uuid,
            'segment_id' => $this->segment?->uuid,

            'smtp_strategy' => CampaignSmtpStrategy::from($this->smtp_strategy)->apiValue(),
            'single_smtp_account_id' => $this->singleSmtpAccount?->uuid,

            'send_mode' => CampaignSendMode::tryFrom((string) $this->send_mode)?->apiValue(),
            'scheduled_at' => self::iso($this->scheduled_at),
            'timezone' => $this->timezone ?? CampaignService::DEFAULT_TIMEZONE,
            'recurrence_rule' => $this->recurrence_rule,
            'business_hours_only' => $this->business_hours_only,
            'business_hours_start' => $this->business_hours_start,
            'business_hours_end' => $this->business_hours_end,
            'business_days' => $this->business_days,

            'approval_required' => $this->approval_required,
            'approved_by' => $this->approvedBy?->uuid,
            'approved_at' => self::iso($this->approved_at),
            'approver' => $this->approvedBy !== null ? ['id' => $this->approvedBy->uuid, 'name' => $this->approvedBy->name] : null,
            // `not_required` | `pending` (needs a `campaigns.approve` user) | `approved`.
            'approval_status' => ! $this->approval_required ? 'not_required' : ($this->approved_by === null ? 'pending' : 'approved'),

            'tracking_enabled' => $this->tracking_enabled,
            'cloned_from_campaign_id' => $this->clonedFrom?->uuid,
            'parent_campaign_id' => $this->parentCampaign?->uuid,

            'owner' => $this->createdBy !== null ? ['id' => $this->createdBy->uuid, 'name' => $this->createdBy->name] : null,
            'created_by' => $this->createdBy?->uuid,
            'stats_summary' => $this->statsSummary($status),
            'ab_test_enabled' => (bool) $this->ab_test_enabled,
            'available_actions' => app(CampaignService::class)->availableActions($this->resource),

            'sent_at' => self::iso($this->sent_at),
            'completed_at' => self::iso($this->completed_at),
            'created_at' => self::iso($this->created_at),
            'updated_at' => self::iso($this->updated_at),
        ];
    }

    /**
     * @return array<string, int|float>|null
     */
    protected function statsSummary(CampaignStatus $status): ?array
    {
        if (in_array($status, [CampaignStatus::Draft, CampaignStatus::Scheduled], true) && (int) $this->sent_count === 0) {
            return null;
        }

        $delivered = (int) $this->delivered_count;

        return [
            'recipients' => (int) $this->recipients_count,
            'sent' => (int) $this->sent_count,
            'delivered' => $delivered,
            'unique_opens' => (int) $this->unique_opens,
            'unique_clicks' => (int) $this->unique_clicks,
            'unsubscriptions' => (int) $this->unsubscribes,
            'hard_bounces' => (int) $this->hard_bounces,
            'soft_bounces' => (int) $this->soft_bounces,
            'complaints' => (int) $this->complaints,
            'open_rate' => self::ratio((int) $this->unique_opens, $delivered),
            'click_rate' => self::ratio((int) $this->unique_clicks, $delivered),
        ];
    }

    public static function ratio(int $numerator, int $denominator): float
    {
        return $denominator > 0 ? round($numerator / $denominator, 4) : 0.0;
    }

    public static function iso(?DateTimeInterface $date): ?string
    {
        return $date === null ? null : Carbon::instance($date)->utc()->toIso8601ZuluString();
    }
}
