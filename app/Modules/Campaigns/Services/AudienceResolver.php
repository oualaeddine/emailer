<?php

namespace App\Modules\Campaigns\Services;

use App\Domain\Enums\RecipientStatus;
use App\Modules\Campaigns\AbTesting\Services\VariantAssigner;
use App\Modules\Campaigns\Contracts\AudienceResolver as AudienceResolverContract;
use App\Modules\Campaigns\Data\AudienceEstimate;
use App\Modules\Campaigns\Enums\AudienceMode;
use App\Modules\Campaigns\Enums\AudienceTargetType;
use App\Modules\Campaigns\Enums\CampaignRecipientStatus;
use App\Modules\Campaigns\Enums\CampaignSendMode;
use App\Modules\Campaigns\Events\AudienceMaterialised;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\CampaignRecipient;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Recipients\Models\RecipientList;
use App\Modules\Recipients\Models\Segment;
use App\Modules\Recipients\Services\SegmentEvaluationService;
use App\Modules\Suppression\Models\SuppressionEntry;
use App\Modules\Tracking\Models\Message;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * docs/43-brevo-parity-plan.md §43.6.1 / docs/45 §45.9 — audience resolution:
 * union of included lists' members + included segments' evaluated members,
 * minus excluded lists/segments, minus suppressed recipients, deduplicated by
 * recipient id. A child campaign (`parent_campaign_id`, "send to new
 * contacts") additionally excludes everyone already in the parent's
 * `campaign_recipients`.
 *
 * Works on recipient-id sets (never hydrates the whole audience), so it
 * stays usable for 100k+ audiences.
 */
class AudienceResolver implements AudienceResolverContract
{
    public const UNENGAGED_DAYS = 90;

    public const DEFAULT_UNENGAGED_WARNING_PERCENT = 0.30;

    private const CHUNK = 1000;

    public function __construct(private readonly SegmentEvaluationService $segments) {}

    public static function estimateCacheKey(Campaign $campaign): string
    {
        return "campaigns.{$campaign->id}.audience_estimate";
    }

    public function resolve(Campaign $campaign): int
    {
        $ids = $this->compute($campaign, null)['final'];
        sort($ids);

        $now = now();
        $batchStart = null;
        $batchSize = (int) $campaign->batch_size;
        $interval = (int) $campaign->batch_interval_minutes;
        $batching = $campaign->send_mode === CampaignSendMode::Batches->value && $batchSize > 0 && $interval > 0;

        if ($batching) {
            $batchStart = $campaign->scheduled_at !== null && $campaign->scheduled_at->isFuture()
                ? $campaign->scheduled_at->copy()
                : $now->copy();
        }

        // One transaction: a worker killed mid-way leaves no partial
        // materialisation; `recipients_count` (the completion marker, see
        // Campaign::isAudienceMaterialised()) is written last.
        $count = DB::transaction(function () use ($campaign, $ids, $batching, $batchStart, $batchSize, $interval, $now): int {
            foreach (array_chunk($ids, self::CHUNK, true) as $chunk) {
                $rows = [];
                foreach ($chunk as $index => $recipientId) {
                    $rows[] = [
                        'campaign_id' => $campaign->id,
                        'recipient_id' => $recipientId,
                        'status' => CampaignRecipientStatus::Pending->value,
                        'scheduled_for' => $batching
                            ? $batchStart->copy()->addMinutes(intdiv($index, $batchSize) * $interval)
                            : null,
                        'materialised_at' => $now,
                    ];
                }
                CampaignRecipient::query()->insertOrIgnore($rows);
            }

            $this->linkAlreadyDispatchedMessages($campaign);
            app(VariantAssigner::class)->assign($campaign);

            $count = CampaignRecipient::query()->where('campaign_id', $campaign->id)->count();
            $campaign->forceFill(['recipients_count' => $count])->save();

            return $count;
        });

        AudienceMaterialised::dispatch($campaign->id, $count);

        return $count;
    }

    public function estimate(Campaign $campaign, ?array $audience = null): AudienceEstimate
    {
        $result = $this->compute($campaign, $audience);
        $total = count($result['final']);
        $unengaged = $this->countUnengaged($result['final']);
        $percent = $total > 0 ? round($unengaged / $total, 4) : 0.0;
        $threshold = (float) config('campaigns.unengaged_warning_percent', self::DEFAULT_UNENGAGED_WARNING_PERCENT);

        $estimate = new AudienceEstimate(
            included: $result['raw_included'],
            excluded: $result['excluded'],
            suppressed: $result['suppressed'],
            duplicates: $result['duplicates'],
            total: $total,
            unengaged: $unengaged,
            unengagedPercent: $percent,
            unengagedWarning: $total > 0 && $percent > $threshold,
            byTarget: $result['by_target'],
            computedAt: now(),
        );

        if ($audience === null) {
            Cache::put(self::estimateCacheKey($campaign), [
                'count' => $total,
                'at' => $estimate->computedAt->toIso8601ZuluString(),
            ], now()->addDay());
        }

        return $estimate;
    }

    public function recipientIds(Campaign $campaign): array
    {
        return $this->compute($campaign, null)['final'];
    }

    /**
     * First deliverable member of the audience, for previews (merge tags rendered with real data).
     * Cheap on purpose: stops at the first included target that yields an active, non-suppressed
     * recipient instead of resolving the whole audience.
     */
    public function sampleRecipient(Campaign $campaign): ?Recipient
    {
        foreach ($this->targets($campaign) as $target) {
            if ($target['mode'] !== AudienceMode::Include) {
                continue;
            }

            $query = $target['model'] instanceof RecipientList
                ? Recipient::query()->whereIn('recipients.id', DB::table('recipient_list_recipient')
                    ->where('recipient_list_id', $target['model']->id)->select('recipient_id'))
                : $this->segments->compile($target['model']->rules ?? []);

            $recipient = $query
                ->where('recipients.status', RecipientStatus::Active->value)
                ->whereNotIn('recipients.email', SuppressionEntry::query()->select('email'))
                ->orderBy('recipients.id')
                ->first();

            if ($recipient !== null) {
                return $recipient;
            }
        }

        return null;
    }

    public function recipients(Campaign $campaign): Collection
    {
        $ids = $this->recipientIds($campaign);

        if ($ids === []) {
            return new Collection;
        }

        return Recipient::query()->whereIn('id', $ids)->orderBy('id')->get();
    }

    /**
     * Saved (or proposed) targets of a campaign as hydrated descriptors.
     *
     * @param  array<string, list<string>>|null  $audience  uuids keyed lists|exclusion_lists|segments|excluded_segments
     * @return list<array{type: AudienceTargetType, mode: AudienceMode, model: RecipientList|Segment}>
     */
    public function targets(Campaign $campaign, ?array $audience = null): array
    {
        $targets = [];

        if ($audience !== null) {
            $map = [
                'lists' => [AudienceTargetType::List, AudienceMode::Include],
                'exclusion_lists' => [AudienceTargetType::List, AudienceMode::Exclude],
                'segments' => [AudienceTargetType::Segment, AudienceMode::Include],
                'excluded_segments' => [AudienceTargetType::Segment, AudienceMode::Exclude],
            ];

            foreach ($map as $key => [$type, $mode]) {
                $uuids = array_values(array_filter((array) ($audience[$key] ?? [])));
                if ($uuids === []) {
                    continue;
                }
                $models = $type === AudienceTargetType::List
                    ? RecipientList::query()->whereIn('uuid', $uuids)->get()
                    : Segment::query()->with('recipientList')->whereIn('uuid', $uuids)->get();
                foreach ($models as $model) {
                    $targets[] = ['type' => $type, 'mode' => $mode, 'model' => $model];
                }
            }

            return $targets;
        }

        $rows = $campaign->audiences()->orderBy('id')->get();

        if ($rows->isEmpty()) {
            // Legacy single-target campaign not yet backfilled (docs/45 §45.10).
            if ($campaign->recipient_list_id !== null && ($list = RecipientList::query()->find($campaign->recipient_list_id)) !== null) {
                $targets[] = ['type' => AudienceTargetType::List, 'mode' => AudienceMode::Include, 'model' => $list];
            }
            if ($campaign->segment_id !== null && ($segment = Segment::query()->with('recipientList')->find($campaign->segment_id)) !== null) {
                $targets[] = ['type' => AudienceTargetType::Segment, 'mode' => AudienceMode::Include, 'model' => $segment];
            }

            return $targets;
        }

        $lists = RecipientList::query()
            ->whereIn('id', $rows->where('target_type', AudienceTargetType::List->value)->pluck('target_id'))
            ->get()->keyBy('id');
        $segments = Segment::query()->with('recipientList')
            ->whereIn('id', $rows->where('target_type', AudienceTargetType::Segment->value)->pluck('target_id'))
            ->get()->keyBy('id');

        foreach ($rows as $row) {
            $type = AudienceTargetType::from($row->target_type);
            $model = $type === AudienceTargetType::List ? $lists->get($row->target_id) : $segments->get($row->target_id);
            if ($model !== null) {
                $targets[] = ['type' => $type, 'mode' => AudienceMode::from($row->mode), 'model' => $model];
            }
        }

        return $targets;
    }

    public static function targetName(RecipientList|Segment $model): string
    {
        if ($model instanceof RecipientList) {
            return (string) $model->name;
        }

        return (string) ($model->getAttribute('name') ?? $model->recipientList?->name ?? 'Segment');
    }

    /**
     * Member ids of one target (segments: evaluated rule tree, which already
     * excludes `status = suppressed`).
     *
     * @return list<int>
     */
    public function memberIds(RecipientList|Segment $model): array
    {
        if ($model instanceof RecipientList) {
            return DB::table('recipient_list_recipient')
                ->where('recipient_list_id', $model->id)
                ->distinct()
                ->pluck('recipient_id')
                ->map(fn ($id): int => (int) $id)
                ->all();
        }

        return $this->segments->compile($model->rules ?? [])
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @param  array<string, list<string>>|null  $audience
     * @return array{final: list<int>, raw_included: int, excluded: int, suppressed: int, duplicates: int, by_target: list<array{type: string, mode: string, id: string, name: string, count: int}>}
     */
    private function compute(Campaign $campaign, ?array $audience): array
    {
        $raw = 0;
        $included = [];
        $excludedSet = [];
        $byTarget = [];

        foreach ($this->targets($campaign, $audience) as $target) {
            $ids = $this->memberIds($target['model']);
            $byTarget[] = [
                'type' => $target['type']->value,
                'mode' => $target['mode']->value,
                'id' => (string) $target['model']->uuid,
                'name' => self::targetName($target['model']),
                'count' => count($ids),
            ];

            if ($target['mode'] === AudienceMode::Include) {
                $raw += count($ids);
                foreach ($ids as $id) {
                    $included[$id] = true;
                }
            } else {
                foreach ($ids as $id) {
                    $excludedSet[$id] = true;
                }
            }
        }

        if ($campaign->parent_campaign_id !== null) {
            CampaignRecipient::query()
                ->where('campaign_id', $campaign->parent_campaign_id)
                ->pluck('recipient_id')
                ->each(function ($id) use (&$excludedSet): void {
                    $excludedSet[(int) $id] = true;
                });
        }

        $afterExclusion = array_diff_key($included, $excludedSet);
        $suppressedSet = array_flip($this->suppressedIds(array_keys($afterExclusion)));
        $final = array_keys(array_diff_key($afterExclusion, $suppressedSet));

        return [
            'final' => array_map('intval', $final),
            'raw_included' => $raw,
            'excluded' => count($included) - count($afterExclusion),
            'suppressed' => count($suppressedSet),
            'duplicates' => $raw - count($included),
            'by_target' => $byTarget,
        ];
    }

    /**
     * Recipients suppressed or invalid by status, or by a suppression entry on
     * their email (docs/23-suppression-list.md §23.6).
     *
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function suppressedIds(array $ids): array
    {
        $suppressed = [];

        foreach (array_chunk($ids, self::CHUNK) as $chunk) {
            $found = Recipient::query()
                ->whereIn('id', $chunk)
                ->where(function ($q): void {
                    // `invalid`: undeliverable (docs/22) or test-send-only addresses.
                    $q->whereIn('status', [RecipientStatus::Suppressed->value, RecipientStatus::Invalid->value])
                        ->orWhereIn('email', SuppressionEntry::query()->select('email'));
                })
                ->pluck('id')
                ->all();
            array_push($suppressed, ...array_map('intval', $found));
        }

        return $suppressed;
    }

    /**
     * "No open in 90 days": recipients that have already been mailed at least
     * once (non-test) and have no message opened within the window.
     *
     * @param  list<int>  $ids
     */
    private function countUnengaged(array $ids): int
    {
        $cutoff = Carbon::now()->subDays(self::UNENGAGED_DAYS);
        $count = 0;

        foreach (array_chunk($ids, self::CHUNK) as $chunk) {
            $mailed = Message::query()->whereIn('recipient_id', $chunk)->where('is_test', false)
                ->distinct()->pluck('recipient_id')->all();
            if ($mailed === []) {
                continue;
            }
            $opened = Message::query()->whereIn('recipient_id', $mailed)->where('opened_at', '>=', $cutoff)
                ->distinct()->pluck('recipient_id')->all();
            $count += count(array_diff($mailed, $opened));
        }

        return $count;
    }

    /**
     * Resumption safety: recipients that already have a (non-test) message
     * for this campaign are never re-sent.
     */
    private function linkAlreadyDispatchedMessages(Campaign $campaign): void
    {
        Message::query()
            ->where('campaign_id', $campaign->id)
            ->where('is_test', false)
            ->select(['id', 'recipient_id'])
            ->chunkById(self::CHUNK, function ($messages) use ($campaign): void {
                foreach ($messages as $message) {
                    CampaignRecipient::query()
                        ->where('campaign_id', $campaign->id)
                        ->where('recipient_id', $message->recipient_id)
                        ->where('status', CampaignRecipientStatus::Pending->value)
                        ->update(['status' => CampaignRecipientStatus::Queued->value, 'message_id' => $message->id]);
                }
            });
    }
}
