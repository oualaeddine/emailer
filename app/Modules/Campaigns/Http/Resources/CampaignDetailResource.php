<?php

namespace App\Modules\Campaigns\Http\Resources;

use App\Modules\Campaigns\AbTesting\Services\AbTestService;
use App\Modules\Campaigns\Enums\AudienceMode;
use App\Modules\Campaigns\Enums\AudienceTargetType;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Services\AudienceResolver;
use App\Modules\Campaigns\Services\CampaignService;
use App\Modules\Campaigns\Support\ExternalReferences;
use App\Modules\DeliveryEngine\Rendering\Support\SystemUrls;
use App\Modules\Recipients\Models\RecipientList;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * docs/45-brevo-api-contracts.md §45.4.2 `CampaignV2Detail` = `CampaignV2` + setup + design.
 *
 * @mixin Campaign
 */
class CampaignDetailResource extends CampaignResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $refs = app(ExternalReferences::class);

        return [
            ...parent::toArray($request),
            'html_body' => $this->html_body,
            'design_json' => $this->design_json,
            'reply_to' => $this->reply_to,
            'to_field' => $this->to_field,
            'audience' => $this->audiencePayload(),
            'utm' => [
                'enabled' => (bool) $this->utm_enabled,
                'source' => $this->utm_source,
                'medium' => $this->utm_medium,
                'campaign' => $this->utm_campaign,
                'content' => $this->utm_content,
                'term' => $this->utm_term,
                'id' => $this->utm_id,
            ],
            'mirror_link_enabled' => (bool) $this->mirror_link_enabled,
            'inline_images' => (bool) $this->inline_images,
            'attachment' => $this->attachmentPayload(),
            'email_expiration' => $this->email_expiration_duration !== null
                ? ['duration' => (int) $this->email_expiration_duration, 'unit' => $this->email_expiration_unit]
                : null,
            'unsubscribe_page_id' => $refs->uuidForId(ExternalReferences::UNSUBSCRIBE_PAGES_TABLE, $this->unsubscribe_page_id),
            'update_profile_form_id' => $refs->uuidForId(ExternalReferences::UPDATE_PROFILE_FORMS_TABLE, $this->update_profile_form_id),
            'smtp_account_id' => $this->singleSmtpAccount?->uuid,
            'send_window' => $this->sendWindow(),
            'requires_approval' => (bool) $this->approval_required,
            'send_time_optimized' => (bool) $this->send_time_optimized,
            'batch' => $this->batch_size !== null
                ? ['size' => (int) $this->batch_size, 'interval_minutes' => (int) $this->batch_interval_minutes]
                : null,
            'ab_test' => app(AbTestService::class)->detailPayload($this->resource),
            'test_sent_at' => self::iso($this->test_sent_at),
            // docs/45 §45.6.3 — anonymous public mirror (`/m/c/{campaign}`) once the campaign was sent.
            'share_link' => $this->sent_at !== null ? app(SystemUrls::class)->campaignMirror($this->resource) : null,
            'checklist_status' => $this->checklistStatus(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function audiencePayload(): array
    {
        $resolver = app(AudienceResolver::class);
        $groups = ['lists' => [], 'exclusion_lists' => [], 'segments' => [], 'excluded_segments' => []];

        foreach ($resolver->targets($this->resource) as $target) {
            $model = $target['model'];
            $isList = $target['type'] === AudienceTargetType::List;
            $key = match (true) {
                $isList && $target['mode'] === AudienceMode::Include => 'lists',
                $isList => 'exclusion_lists',
                $target['mode'] === AudienceMode::Include => 'segments',
                default => 'excluded_segments',
            };

            $count = $model instanceof RecipientList
                ? DB::table('recipient_list_recipient')->where('recipient_list_id', $model->id)->count()
                : ($model->cached_count ?? count($resolver->memberIds($model)));

            $groups[$key][] = ['id' => $model->uuid, 'name' => AudienceResolver::targetName($model), 'contact_count' => (int) $count];
        }

        $cached = Cache::get(AudienceResolver::estimateCacheKey($this->resource));
        if ($cached === null) {
            $resolver->estimate($this->resource);
            $cached = Cache::get(AudienceResolver::estimateCacheKey($this->resource));
        }

        $materialised = $this->campaignRecipients()->count();

        return [
            ...$groups,
            'estimated_count' => $cached['count'] ?? null,
            'estimated_at' => $cached['at'] ?? null,
            'materialised_count' => $materialised > 0 ? $materialised : null,
        ];
    }

    /**
     * @return array{id: string, filename: string, size: int}|null
     */
    private function attachmentPayload(): ?array
    {
        if ($this->attachment_id === null) {
            return null;
        }

        $row = DB::table('attachments')->where('id', $this->attachment_id)->first();

        return $row === null ? null : [
            'id' => (string) $row->uuid,
            'filename' => (string) $row->original_filename,
            'size' => (int) $row->size_bytes,
        ];
    }

    /**
     * `send_window` ⇄ legacy `business_hours_*` columns (days 0=Sun…6=Sat).
     *
     * @return array{days: list<int>, start: string|null, end: string|null}|null
     */
    private function sendWindow(): ?array
    {
        if (! $this->business_hours_only) {
            return null;
        }

        $names = ['sun' => 0, 'mon' => 1, 'tue' => 2, 'wed' => 3, 'thu' => 4, 'fri' => 5, 'sat' => 6];
        $days = [];
        foreach ((array) ($this->business_days ?? []) as $day) {
            if (is_numeric($day)) {
                $days[] = (int) $day;
            } elseif (isset($names[strtolower(substr((string) $day, 0, 3))])) {
                $days[] = $names[strtolower(substr((string) $day, 0, 3))];
            }
        }

        return [
            'days' => array_values(array_unique($days)),
            'start' => $this->business_hours_start !== null ? substr((string) $this->business_hours_start, 0, 5) : null,
            'end' => $this->business_hours_end !== null ? substr((string) $this->business_hours_end, 0, 5) : null,
        ];
    }

    /**
     * Cached from WP-45 when its service is bound; local fallback otherwise.
     */
    private function checklistStatus(): string
    {
        if (app()->bound(CampaignService::CHECKLIST_SERVICE)) {
            try {
                $result = app(CampaignService::CHECKLIST_SERVICE)->evaluate($this->resource);
                $status = is_array($result) ? ($result['status'] ?? null) : ($result->status ?? null);
                if (is_object($status) && property_exists($status, 'value')) {
                    $status = $status->value;
                }
                if (is_string($status)) {
                    return $status;
                }
            } catch (Throwable) {
                // fall through to the local estimate
            }
        }

        $complete = filled($this->subject)
            && filled($this->html_body)
            && app(CampaignService::class)->hasAudience($this->resource);

        return $complete ? 'complete' : 'incomplete';
    }
}
