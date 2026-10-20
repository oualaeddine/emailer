<?php

namespace App\Modules\Campaigns\AbTesting\Services;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Campaigns\AbTesting\Events\AbTestWinnerPicked;
use App\Modules\Campaigns\AbTesting\Jobs\PickAbWinnerJob;
use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Enums\CampaignType;
use App\Modules\Campaigns\Exceptions\CampaignStateConflictException;
use App\Modules\Campaigns\Jobs\DispatchCampaignJob;
use App\Modules\Campaigns\Models\AbTestVariant;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Identity\Models\User;
use App\Modules\Reporting\Services\ReportQueryService;
use App\Modules\Tracking\Models\MessageEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * docs/43-brevo-parity-plan.md §43.6.2 / docs/45 §45.7.2 (WP-80) — A/B test
 * configuration, validation, winner selection and payloads.
 *
 * `campaigns.ab_test_config` = AbTestConfig (`type` subject|content,
 * `sample_percent` 5–50, `winner_metric` opens|clicks,
 * `test_duration_minutes` 60–10080, `auto_send_winner`). Versions live in
 * `ab_test_variants` (A, B). The test sample is split by
 * {@see VariantAssigner} at materialisation and sent by
 * {@see AbTestDispatcher}; {@see PickAbWinnerJob}
 * (or a manual pick) sets `winner_variant_id` and releases the remainder.
 */
class AbTestService
{
    public const TYPES = ['subject', 'content'];

    public const METRICS = ['opens', 'clicks'];

    public const SAMPLE_MIN = 5;

    public const SAMPLE_MAX = 50;

    public const DEFAULT_SAMPLE_PERCENT = 20;

    public const DURATION_MIN = 60;

    public const DURATION_MAX = 10080;

    public const DEFAULT_DURATION = 240;

    public const PHASE_NOT_STARTED = 'not_started';

    public const PHASE_TESTING = 'testing';

    public const PHASE_WINNER_PICKED = 'winner_picked';

    public const PHASE_COMPLETED = 'completed';

    public const PICKED_AUTO = 'auto';

    public const PICKED_MANUAL = 'manual';

    /** docs/45 §45.6.5 timeline action `ab_winner_picked`. */
    public const AUDIT_WINNER_PICKED = 'campaign.ab_winner_picked';

    /** Statuses in which the test phase can be running (sample sent, remainder waiting). */
    private const TEST_STATUSES = [CampaignStatus::Running, CampaignStatus::Paused, CampaignStatus::Suspended];

    public function __construct(private readonly AuditLogger $audit) {}

    // ------------------------------------------------------------------ configuration

    /**
     * Validation rules of an AbTestConfig, keys prefixed with `$prefix`.
     *
     * @return array<string, mixed>
     */
    public static function configRules(string $prefix = 'config.'): array
    {
        return [
            $prefix.'type' => ['required', Rule::in(self::TYPES)],
            $prefix.'sample_percent' => ['required', 'integer', 'between:'.self::SAMPLE_MIN.','.self::SAMPLE_MAX],
            $prefix.'winner_metric' => ['required', Rule::in(self::METRICS)],
            $prefix.'test_duration_minutes' => ['required', 'integer', 'between:'.self::DURATION_MIN.','.self::DURATION_MAX],
            $prefix.'auto_send_winner' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $config
     * @return array{type: string, sample_percent: int, winner_metric: string, test_duration_minutes: int, auto_send_winner: bool}
     */
    public static function normaliseConfig(?array $config): array
    {
        $config ??= [];

        return [
            'type' => in_array($config['type'] ?? null, self::TYPES, true) ? $config['type'] : 'subject',
            'sample_percent' => (int) ($config['sample_percent'] ?? self::DEFAULT_SAMPLE_PERCENT),
            'winner_metric' => in_array($config['winner_metric'] ?? null, self::METRICS, true) ? $config['winner_metric'] : 'opens',
            'test_duration_minutes' => (int) ($config['test_duration_minutes'] ?? self::DEFAULT_DURATION),
            'auto_send_winner' => (bool) ($config['auto_send_winner'] ?? true),
        ];
    }

    /**
     * @return array{type: string, sample_percent: int, winner_metric: string, test_duration_minutes: int, auto_send_winner: bool}
     */
    public function config(Campaign $campaign): array
    {
        return self::normaliseConfig($campaign->ab_test_config);
    }

    /**
     * `PUT /campaigns/{id}/ab-test` (draft only, 409 otherwise). Enabling
     * stores the config, upserts versions A and B and sets `type = ab_test`;
     * a subject test also writes version A's subject on the campaign (Brevo
     * `subjectA` = campaign subject). Disabling keeps the stored versions
     * (re-enabling restores them) and sets `type = classic`.
     *
     * @param  array<string, mixed>|null  $config
     * @param  list<array<string, mixed>>  $variants
     */
    public function save(Campaign $campaign, bool $enabled, ?array $config, array $variants): Campaign
    {
        if ($campaign->status !== CampaignStatus::Draft->value || $campaign->isArchived()) {
            throw new CampaignStateConflictException('Le test A/B ne peut être modifié que sur une campagne en brouillon.');
        }

        DB::transaction(function () use ($campaign, $enabled, $config, $variants): void {
            if (! $enabled) {
                $campaign->update(['ab_test_enabled' => false, 'type' => CampaignType::Classic->value]);

                return;
            }

            $config = self::normaliseConfig($config);
            $isContent = $config['type'] === 'content';
            $inputs = collect($variants)->keyBy('label');

            foreach (AbTestVariant::LABELS as $label) {
                $input = (array) $inputs->get($label, []);
                AbTestVariant::query()->updateOrCreate(
                    ['campaign_id' => $campaign->id, 'label' => $label],
                    [
                        'subject' => self::trimmed($input['subject'] ?? null),
                        'preview_text' => self::trimmed($input['preview_text'] ?? null),
                        'html_body' => $isContent ? ($input['html_body'] ?? null) : null,
                        'design_json' => $isContent ? ($input['design_json'] ?? null) : null,
                        'is_winner' => false,
                        'sample_size' => 0,
                    ],
                );
            }

            $updates = [
                'type' => CampaignType::AbTest->value,
                'ab_test_enabled' => true,
                'ab_test_config' => $config,
                'winner_variant_id' => null,
            ];

            $subjectA = self::trimmed($inputs->get(AbTestVariant::LABEL_A)['subject'] ?? null);
            if (! $isContent && $subjectA !== null) {
                $updates['subject'] = $subjectA;
            }

            $campaign->update($updates);
        });

        return $campaign->fresh();
    }

    /**
     * Schedule / send-now guard (called from `CampaignService::assertReadyToDispatch()`):
     * `422` with `errors.ab_test` when an A/B campaign is not ready.
     *
     * @throws ValidationException
     */
    public function assertSchedulable(Campaign $campaign): void
    {
        if (! $campaign->ab_test_enabled && $campaign->type !== CampaignType::AbTest->value) {
            return;
        }

        $errors = [];

        if (! $campaign->ab_test_enabled) {
            $errors[] = 'Configurez et activez le test A/B avant de programmer cette campagne.';
        } else {
            $validator = Validator::make((array) $campaign->ab_test_config, self::configRules(''));
            array_push($errors, ...$validator->errors()->all());

            $variants = $this->variants($campaign);
            $a = $variants->get(AbTestVariant::LABEL_A);
            $b = $variants->get(AbTestVariant::LABEL_B);
            $type = $this->config($campaign)['type'];

            if ($a === null || $b === null) {
                $errors[] = 'Les versions A et B du test A/B sont obligatoires.';
            } elseif ($type === 'subject') {
                if (blank($a->subject) || blank($b->subject)) {
                    $errors[] = 'Les objets des versions A et B sont obligatoires.';
                } elseif (trim((string) $a->subject) === trim((string) $b->subject)) {
                    $errors[] = 'Les objets des versions A et B doivent être différents.';
                }
            } else {
                if (blank($this->htmlFor($campaign, $a)) || blank($b->html_body)) {
                    $errors[] = 'Le contenu des versions A et B est obligatoire.';
                } elseif (trim($this->htmlFor($campaign, $a)) === trim((string) $b->html_body)) {
                    $errors[] = 'Les contenus des versions A et B doivent être différents.';
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages(['ab_test' => $errors]);
        }
    }

    // ------------------------------------------------------------------ versions

    /**
     * @return Collection<string, AbTestVariant> keyed by label
     */
    public function variants(Campaign $campaign): Collection
    {
        return $campaign->abTestVariants()->get()->keyBy('label');
    }

    /**
     * The version a preview / test send was asked for (`variant` = "A"/"B"),
     * `null` when none was asked. 422 `errors.variant` for an unknown label,
     * a campaign without an active A/B test or a missing version.
     */
    public function variantForPreview(Campaign $campaign, mixed $label): ?AbTestVariant
    {
        if ($label === null || $label === '') {
            return null;
        }

        if (! is_string($label) || ! in_array($label, ['A', 'B'], true)) {
            throw ValidationException::withMessages(['variant' => ['Version inconnue : choisissez A ou B.']]);
        }

        if (! $campaign->ab_test_enabled) {
            throw ValidationException::withMessages(['variant' => ["Cette campagne n'a pas de test A/B actif."]]);
        }

        $variant = $this->variants($campaign)->get($label);
        if ($variant === null) {
            throw ValidationException::withMessages(['variant' => ["La version {$label} n'existe pas pour cette campagne."]]);
        }

        return $variant;
    }

    /** Subject sent for a version (falls back to the campaign subject). */
    public function subjectFor(Campaign $campaign, ?AbTestVariant $variant): string
    {
        return $variant !== null && filled($variant->subject) ? (string) $variant->subject : (string) $campaign->subject;
    }

    /** Html sent for a version (falls back to the campaign design). */
    public function htmlFor(Campaign $campaign, ?AbTestVariant $variant): string
    {
        return $variant !== null && filled($variant->html_body) ? (string) $variant->html_body : (string) $campaign->html_body;
    }

    /**
     * Html sent for a version with the version's `preview_text` injected as
     * a hidden preheader right after `<body>` (same markup as the
     * transactional / automation paths).
     */
    public function htmlWithPreviewFor(Campaign $campaign, ?AbTestVariant $variant): string
    {
        return self::withPreheader($this->htmlFor($campaign, $variant), $variant?->preview_text);
    }

    /** Hidden preheader div inserted after `<body>` (prepended when the html has none). */
    public static function withPreheader(string $html, ?string $previewText): string
    {
        $text = trim((string) $previewText);
        if ($text === '') {
            return $html;
        }

        $div = '<div style="display:none;max-height:0;overflow:hidden;mso-hide:all">'.e($text).'</div>';

        $count = 0;
        $html = preg_replace('/<body\b[^>]*>/i', '$0'.$div, $html, 1, $count) ?? $html;

        return $count > 0 ? $html : $div.$html;
    }

    /**
     * "Envoyer aux nouveaux contacts" of an A/B campaign whose winner is
     * picked: the child is a classic campaign carrying the winner's subject,
     * preview text and content (no new test). `null` while no winner exists
     * (the child then copies the test as is).
     *
     * @return array<string, mixed>|null
     */
    public function winnerCopyAttributes(Campaign $campaign): ?array
    {
        if (! $campaign->ab_test_enabled || $campaign->winner_variant_id === null) {
            return null;
        }

        $winner = $campaign->abTestVariants()->whereKey($campaign->winner_variant_id)->first();
        if ($winner === null) {
            return null;
        }

        $ownContent = filled($winner->html_body);

        return [
            'type' => CampaignType::Classic->value,
            'ab_test_enabled' => false,
            'ab_test_config' => null,
            'subject' => $this->subjectFor($campaign, $winner),
            'preview_text' => filled($winner->preview_text) ? $winner->preview_text : $campaign->preview_text,
            'html_body' => $this->htmlFor($campaign, $winner),
            'design_json' => $ownContent && $winner->design_json !== null ? $winner->design_json : $campaign->design_json,
        ];
    }

    /**
     * Copies versions A/B onto a duplicate / child campaign (winner and
     * sample sizes reset). Config and `ab_test_enabled` travel with
     * `Campaign::COPYABLE_COLUMNS`.
     */
    public function copyVariants(Campaign $from, Campaign $to): void
    {
        foreach ($from->abTestVariants()->get() as $variant) {
            AbTestVariant::query()->create([
                'campaign_id' => $to->id,
                'label' => $variant->label,
                'subject' => $variant->subject,
                'preview_text' => $variant->preview_text,
                'html_body' => $variant->html_body,
                'design_json' => $variant->design_json,
            ]);
        }
    }

    // ------------------------------------------------------------------ phase & winner

    public function phase(Campaign $campaign): string
    {
        if ($campaign->status === CampaignStatus::Completed->value) {
            return self::PHASE_COMPLETED;
        }

        if ($campaign->winner_variant_id !== null) {
            return self::PHASE_WINNER_PICKED;
        }

        if (in_array($campaign->status, [CampaignStatus::Draft->value, CampaignStatus::Scheduled->value], true)) {
            return self::PHASE_NOT_STARTED;
        }

        return $campaign->campaignRecipients()->whereNotNull('variant_id')->exists()
            ? self::PHASE_TESTING
            : self::PHASE_NOT_STARTED;
    }

    /** The test phase is running: sample sent / sending, remainder waiting for a winner. */
    public function isTesting(Campaign $campaign): bool
    {
        return $campaign->ab_test_enabled
            && $campaign->winner_variant_id === null
            && in_array($campaign->statusEnum(), self::TEST_STATUSES, true)
            && $this->phase($campaign) === self::PHASE_TESTING;
    }

    /** When the automatic pick is due: `sent_at + test_duration_minutes`. */
    public function winnerDueAt(Campaign $campaign): Carbon
    {
        return ($campaign->sent_at ?? now())->copy()->addMinutes($this->config($campaign)['test_duration_minutes']);
    }

    /**
     * Unique opens / clicks per version of the test sample, live from
     * `messages` (+ `message_events`, for events not yet stamped on the message).
     *
     * @return array<int, array{sent: int, opens: int, clicks: int}> keyed by variant id
     */
    public function metrics(Campaign $campaign): array
    {
        $rows = DB::table('messages as m')
            ->where('m.campaign_id', $campaign->id)
            ->where('m.is_test', false)
            ->whereNotNull('m.variant_id')
            ->groupBy('m.variant_id')
            ->select('m.variant_id')
            ->selectRaw('COUNT(*) as sent')
            ->selectRaw(
                'SUM(CASE WHEN m.opened_at IS NOT NULL OR EXISTS (SELECT 1 FROM message_events e WHERE e.message_id = m.id AND e.event_type = ?) THEN 1 ELSE 0 END) as opens',
                [MessageEvent::OPENED],
            )
            ->selectRaw(
                'SUM(CASE WHEN m.clicked_at IS NOT NULL OR EXISTS (SELECT 1 FROM message_events e WHERE e.message_id = m.id AND e.event_type = ?) THEN 1 ELSE 0 END) as clicks',
                [MessageEvent::CLICKED],
            )
            ->get();

        $metrics = [];
        foreach ($rows as $row) {
            $metrics[(int) $row->variant_id] = ['sent' => (int) $row->sent, 'opens' => (int) $row->opens, 'clicks' => (int) $row->clicks];
        }

        return $metrics;
    }

    /**
     * Version with the best unique open (or click) rate over its sent
     * sample messages; a tie goes to A.
     */
    public function determineWinner(Campaign $campaign): ?AbTestVariant
    {
        $variants = $this->variants($campaign);
        $a = $variants->get(AbTestVariant::LABEL_A);
        $b = $variants->get(AbTestVariant::LABEL_B);

        if ($a === null || $b === null) {
            return $a ?? $b;
        }

        $metric = $this->config($campaign)['winner_metric'];
        $metrics = $this->metrics($campaign);
        $empty = ['sent' => 0, 'opens' => 0, 'clicks' => 0];
        $ma = $metrics[$a->id] ?? $empty;
        $mb = $metrics[$b->id] ?? $empty;

        // rate(B) > rate(A), cross-multiplied to stay in integers.
        $rateA = $ma['sent'] > 0 ? $ma[$metric] / $ma['sent'] : 0.0;
        $rateB = $mb['sent'] > 0 ? $mb[$metric] / $mb['sent'] : 0.0;
        $bWins = $ma['sent'] > 0 && $mb['sent'] > 0
            ? $mb[$metric] * $ma['sent'] > $ma[$metric] * $mb['sent']
            : $rateB > $rateA;

        return $bWins ? $b : $a;
    }

    /**
     * Sets the winner (once), records the `campaign.ab_winner_picked` audit
     * entry (report timeline), fires {@see AbTestWinnerPicked} and releases
     * the remainder of the audience (a running campaign is re-dispatched; a
     * paused / suspended one sends it on resume).
     */
    public function pickWinner(Campaign $campaign, AbTestVariant $variant, string $pickedBy, ?User $actor = null): Campaign
    {
        if ($variant->campaign_id !== $campaign->id) {
            throw ValidationException::withMessages(['variant_id' => ['Cette version n’appartient pas à la campagne.']]);
        }

        if ($pickedBy === self::PICKED_MANUAL && ! $this->isTesting($campaign)) {
            throw new CampaignStateConflictException('Le gagnant ne peut être choisi que pendant la phase de test A/B.');
        }

        $metric = $this->config($campaign)['winner_metric'];

        $picked = DB::transaction(function () use ($campaign, $variant, $pickedBy, $actor, $metric): bool {
            $locked = Campaign::query()->whereKey($campaign->id)->lockForUpdate()->first();

            if ($locked === null || $locked->winner_variant_id !== null) {
                return false;
            }

            AbTestVariant::query()->where('campaign_id', $campaign->id)->update(['is_winner' => false]);
            $variant->update(['is_winner' => true]);
            $locked->update(['winner_variant_id' => $variant->id]);

            $this->audit->record(self::AUDIT_WINNER_PICKED, $locked, $actor, null, [
                'variant_id' => $variant->uuid,
                'label' => $variant->label,
                'picked_by' => $pickedBy,
                'winner_metric' => $metric,
            ]);

            return true;
        });

        if (! $picked) {
            if ($pickedBy === self::PICKED_MANUAL) {
                throw new CampaignStateConflictException('Le gagnant du test A/B a déjà été choisi.');
            }

            return $campaign->fresh();
        }

        AbTestWinnerPicked::dispatch($campaign->id, $variant->id, $pickedBy);

        $fresh = $campaign->fresh();
        if ($fresh->status === CampaignStatus::Running->value) {
            DispatchCampaignJob::dispatch($fresh->id);
        }

        return $fresh;
    }

    // ------------------------------------------------------------------ payloads

    /**
     * `CampaignV2Detail.ab_test` (null when the test is disabled).
     *
     * @return array<string, mixed>|null
     */
    public function detailPayload(Campaign $campaign): ?array
    {
        if (! $campaign->ab_test_enabled) {
            return null;
        }

        $variants = $this->variants($campaign);

        return [
            'config' => $this->config($campaign),
            'variants' => $variants->values()->map(fn (AbTestVariant $v): array => [
                'id' => $v->uuid,
                'label' => $v->label,
                'subject' => (string) ($v->subject ?? ''),
                'preview_text' => $v->preview_text,
                'html_body' => $v->html_body,
                'design_json' => $v->design_json,
                'is_winner' => (bool) $v->is_winner,
            ])->all(),
            'winner_variant_id' => $variants->firstWhere('id', $campaign->winner_variant_id)?->uuid,
            'phase' => $this->phase($campaign),
        ];
    }

    /**
     * `ReportOverview.ab_test` (AbTestReport, docs/45 §45.7.2): per-version
     * BrevoStats / RateSet of the test sample from the `campaign_stats_hourly`
     * rollup (bucketed by `campaign_recipients.variant_id`, refreshed by the
     * overview's `globalStats()` call), winner and pick details from the
     * `campaign.ab_winner_picked` audit entry.
     *
     * @return array<string, mixed>|null
     */
    public function report(Campaign $campaign): ?array
    {
        if (! $campaign->ab_test_enabled) {
            return null;
        }

        $variants = $this->variants($campaign);
        $sums = $this->hourlySums($campaign, $variants->pluck('id')->all());

        $log = AuditLog::query()
            ->where('auditable_type', Campaign::class)
            ->where('auditable_id', $campaign->id)
            ->where('action', self::AUDIT_WINNER_PICKED)
            ->latest('id')
            ->first();

        $winner = $variants->firstWhere('id', $campaign->winner_variant_id);
        $pickedBy = $winner !== null ? ($log?->new_values['picked_by'] ?? null) : null;

        return [
            'config' => $this->config($campaign),
            'phase' => $this->phase($campaign),
            'winner_variant_id' => $winner?->uuid,
            'picked_at' => $winner !== null ? ReportQueryService::iso($log?->created_at ?? $winner->updated_at) : null,
            'picked_by' => in_array($pickedBy, [self::PICKED_AUTO, self::PICKED_MANUAL], true) ? $pickedBy : null,
            'variants' => $variants->values()->map(function (AbTestVariant $v) use ($campaign, $sums): array {
                $stats = ReportQueryService::brevoStats($sums[$v->id] ?? []);

                return [
                    'variant_id' => $v->uuid,
                    'label' => $v->label,
                    'subject' => $this->subjectFor($campaign, $v),
                    'globalStats' => $stats,
                    'rates' => ReportQueryService::rates($stats),
                ];
            })->all(),
        ];
    }

    /**
     * @param  list<int>  $variantIds
     * @return array<int, array<string, int>>
     */
    private function hourlySums(Campaign $campaign, array $variantIds): array
    {
        if ($variantIds === [] || ! Schema::hasTable('campaign_stats_hourly')) {
            return [];
        }

        $counters = [
            'sent', 'delivered', 'opens', 'unique_opens', 'proxy_opens', 'unique_clicks',
            'hard_bounces', 'soft_bounces', 'complaints', 'unsubscriptions', 'deferred',
        ];

        $rows = DB::table('campaign_stats_hourly')
            ->where('campaign_id', $campaign->id)
            ->whereIn('variant_id', $variantIds)
            ->groupBy('variant_id')
            ->select('variant_id')
            ->selectRaw(implode(', ', array_map(fn (string $c): string => "COALESCE(SUM({$c}), 0) as {$c}", $counters)))
            ->get();

        $sums = [];
        foreach ($rows as $row) {
            $sums[(int) $row->variant_id] = array_map('intval', array_intersect_key((array) $row, array_flip($counters)));
        }

        return $sums;
    }

    private static function trimmed(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
