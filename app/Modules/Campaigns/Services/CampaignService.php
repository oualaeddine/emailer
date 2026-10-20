<?php

namespace App\Modules\Campaigns\Services;

use App\Domain\Enums\MessageStatus;
use App\Domain\Enums\RecipientSource;
use App\Domain\Enums\RecipientStatus;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Campaigns\AbTesting\Services\AbTestService;
use App\Modules\Campaigns\Contracts\AudienceResolver as AudienceResolverContract;
use App\Modules\Campaigns\Enums\AudienceMode;
use App\Modules\Campaigns\Enums\AudienceTargetType;
use App\Modules\Campaigns\Enums\CampaignEditorType;
use App\Modules\Campaigns\Enums\CampaignRecipientStatus;
use App\Modules\Campaigns\Enums\CampaignSendMode;
use App\Modules\Campaigns\Enums\CampaignSmtpStrategy;
use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Enums\CampaignSuspendedReason;
use App\Modules\Campaigns\Events\CampaignDesignSaved;
use App\Modules\Campaigns\Events\CampaignStatusChanged;
use App\Modules\Campaigns\Events\CampaignTestSent;
use App\Modules\Campaigns\Exceptions\CampaignChecklistBlockedException;
use App\Modules\Campaigns\Exceptions\CampaignNotReadyException;
use App\Modules\Campaigns\Exceptions\CampaignStateConflictException;
use App\Modules\Campaigns\Exceptions\InvalidCampaignTransitionException;
use App\Modules\Campaigns\Jobs\DispatchCampaignJob;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\CampaignAudience;
use App\Modules\Campaigns\Models\CampaignRecipient;
use App\Modules\Campaigns\Support\SendWindow;
use App\Modules\DeliveryEngine\Jobs\SendEmailJob;
use App\Modules\DeliveryEngine\Rendering\RenderingPipeline;
use App\Modules\Identity\Models\User;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Suppression\Models\SuppressionEntry;
use App\Modules\Templates\Models\Template;
use App\Modules\Tracking\Models\Message;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * docs/15-campaign-management.md — Campaign Management module, extended by
 * docs/43-brevo-parity-plan.md §43.4 / docs/45 §45.4.2 (Campaigns v2).
 *
 * The single authority for every Campaign lifecycle transition (§15.1);
 * every transition fires {@see CampaignStatusChanged} (docs/45 §45.9).
 */
class CampaignService
{
    /**
     * §15.4/§30.4 — the fan-out chunk size `DispatchCampaignJob` processes
     * per invocation before checking campaign status and re-enqueuing itself.
     */
    public const BATCH_SIZE = 500;

    /**
     * docs/45 §45.9 — WP-45 binds this in AppServiceProvider; until then the
     * schedule call is skipped (`app()->bound()` guard).
     */
    public const CHECKLIST_SERVICE = 'App\\Modules\\Campaigns\\Services\\Checklist\\CampaignChecklistService';

    /**
     * docs/45 §45.9 — WP-81 dispatcher hook (guarded the same way).
     */
    public const BEST_SEND_TIME_SERVICE = 'App\\Modules\\DeliveryEngine\\Scheduling\\Contracts\\BestSendTimeService';

    public const DEFAULT_TIMEZONE = 'Africa/Algiers';

    /**
     * docs/45 §45.7.2 — delivery options an A/B test campaign cannot use
     * (`422` with `errors.mode`): both spread the sample over time.
     */
    public const AB_TEST_EXCLUDED_MODES = ['best_time', 'batches'];

    /** docs/27-audit-logs.md §27.3 — `campaign.approved`; the reset has its own action. */
    public const AUDIT_APPROVED = 'campaign.approved';

    public const AUDIT_APPROVAL_RESET = 'campaign.approval_reset';

    /**
     * Attributes whose change invalidates an approval: what the reviewer saw
     * (sender, subject, content) and who receives it (audience, see
     * {@see self::audienceSignature()}).
     *
     * @var list<string>
     */
    public const APPROVAL_SENSITIVE_ATTRIBUTES = [
        'subject', 'preview_text', 'html_body', 'design_json', 'sender_id', 'reply_to', 'to_field', 'attachment_id',
    ];

    public function __construct(
        private readonly RenderingPipeline $rendering,
        private readonly AudienceResolverContract $audience,
        private readonly AuditLogger $audit,
    ) {}

    // ---------------------------------------------------------------------
    // Create / update / design
    // ---------------------------------------------------------------------

    /**
     * `$data` uses internal ids. An optional `audience` key
     * (`lists|exclusion_lists|segments|excluded_segments` → list<int>)
     * creates the audience rows; otherwise legacy `recipient_list_id` /
     * `segment_id` are converted into `include` rows (docs/45 §45.10).
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $createdBy): Campaign
    {
        $audience = $data['audience'] ?? null;
        unset($data['audience']);

        return DB::transaction(function () use ($data, $audience, $createdBy): Campaign {
            $campaign = Campaign::query()->create([
                'type' => 'classic',
                ...$data,
                'status' => CampaignStatus::Draft->value,
                'created_by' => $createdBy->id,
            ]);

            if (is_array($audience)) {
                $this->syncAudience($campaign, $audience);
            } else {
                $this->syncLegacyAudience($campaign);
            }

            return $campaign->fresh();
        });
    }

    /**
     * docs/45 §45.4.2 PATCH — allowed while draft / scheduled / suspended (409 otherwise).
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Campaign $campaign, array $data): Campaign
    {
        $this->assertEditable($campaign);

        $audience = $data['audience'] ?? null;
        unset($data['audience']);

        DB::transaction(function () use ($campaign, $data, $audience): void {
            if (is_array($audience)) {
                // Campaigns edited with a v2 audience drop the legacy columns (§45.10).
                $data['recipient_list_id'] = null;
                $data['segment_id'] = null;
            }

            $audienceBefore = $this->audienceSignature($campaign);

            $campaign->fill($data);
            $changed = $this->approvalRelevantChanges($campaign);
            $campaign->save();

            if (is_array($audience)) {
                $this->syncAudience($campaign, $audience);
            } elseif (array_key_exists('recipient_list_id', $data) || array_key_exists('segment_id', $data)) {
                $this->syncLegacyAudience($campaign->fresh());
            }

            if ($this->audienceSignature($campaign) !== $audienceBefore) {
                $changed[] = 'audience';
            }

            $this->resetApprovalAfterEdit($campaign, $changed);
            $this->assertStillReady($campaign);
        });

        return $campaign->fresh();
    }

    /**
     * @param  array{lists?: list<int>, exclusion_lists?: list<int>, segments?: list<int>, excluded_segments?: list<int>}  $audience
     */
    public function syncAudience(Campaign $campaign, array $audience): void
    {
        $map = [
            'lists' => [AudienceTargetType::List, AudienceMode::Include],
            'exclusion_lists' => [AudienceTargetType::List, AudienceMode::Exclude],
            'segments' => [AudienceTargetType::Segment, AudienceMode::Include],
            'excluded_segments' => [AudienceTargetType::Segment, AudienceMode::Exclude],
        ];

        $campaign->audiences()->delete();

        if ($campaign->status === CampaignStatus::Suspended->value) {
            $this->discardPendingMaterialisation($campaign);
        }

        $seen = [];
        foreach ($map as $key => [$type, $mode]) {
            foreach (array_unique(array_filter((array) ($audience[$key] ?? []))) as $targetId) {
                $uniq = $type->value.':'.$targetId;
                if (isset($seen[$uniq])) {
                    continue;
                }
                $seen[$uniq] = true;
                $campaign->audiences()->create([
                    'target_type' => $type->value,
                    'target_id' => (int) $targetId,
                    'mode' => $mode->value,
                ]);
            }
        }

        $this->forgetEstimate($campaign);
    }

    /**
     * Legacy single-target → `include` rows (docs/45 §45.10).
     */
    public function syncLegacyAudience(Campaign $campaign): void
    {
        $this->syncAudience($campaign, [
            'lists' => $campaign->recipient_list_id !== null ? [$campaign->recipient_list_id] : [],
            'segments' => $campaign->segment_id !== null ? [$campaign->segment_id] : [],
        ]);
    }

    /**
     * `PUT /campaigns/{id}/design` (docs/45 §45.4.2).
     *
     * @param  array<string, mixed>|null  $designJson
     */
    public function saveDesign(Campaign $campaign, string $editorType, ?string $htmlBody, ?array $designJson): Campaign
    {
        $this->assertEditable($campaign);

        DB::transaction(function () use ($campaign, $editorType, $htmlBody, $designJson): void {
            $campaign->fill([
                'editor_type' => $editorType,
                'html_body' => $htmlBody,
                'design_json' => $editorType === CampaignEditorType::Dnd->value ? $designJson : null,
            ]);
            $changed = $this->approvalRelevantChanges($campaign);
            $campaign->save();

            $this->resetApprovalAfterEdit($campaign, $changed);
            $this->assertStillReady($campaign);
        });

        CampaignDesignSaved::dispatch($campaign->id, $editorType);

        return $campaign->fresh();
    }

    /**
     * `POST /campaigns/{id}/design/copy` — template picker (docs/45 §45.4.2).
     * `gallery` sources are looked up in `templates` as well (the gallery is
     * a WP-62 view over templates).
     */
    public function copyDesign(Campaign $campaign, string $sourceType, ?string $sourceId): Campaign
    {
        $this->assertEditable($campaign);

        $attributes = match ($sourceType) {
            'blank' => ['editor_type' => CampaignEditorType::Dnd->value, 'html_body' => null, 'design_json' => null],
            'campaign' => $this->designFromCampaign((string) $sourceId),
            default => $this->designFromTemplate((string) $sourceId),
        };

        DB::transaction(function () use ($campaign, $attributes): void {
            $campaign->fill($attributes);
            $changed = $this->approvalRelevantChanges($campaign);
            $campaign->save();

            $this->resetApprovalAfterEdit($campaign, $changed);
            $this->assertStillReady($campaign);
        });

        CampaignDesignSaved::dispatch($campaign->id, $campaign->editor_type);

        return $campaign->fresh();
    }

    // ---------------------------------------------------------------------
    // Scheduling & lifecycle
    // ---------------------------------------------------------------------

    /**
     * §15.1 draft --> scheduled (legacy signature, kept for callers/tests).
     */
    public function schedule(Campaign $campaign, Carbon $scheduledAt): Campaign
    {
        return $this->scheduleWithOptions($campaign, ['mode' => 'scheduled', 'scheduled_at' => $scheduledAt]);
    }

    /**
     * docs/45 §45.4.2 `CampaignScheduleInput` — the four delivery options.
     * `now` (draft|scheduled), `scheduled` (draft, future `scheduled_at`),
     * `best_time` (flag stored; WP-81 plans per contact), `batches`
     * (`batch_size` rows released every `batch_interval_minutes`).
     *
     * @param  array{mode?: string, scheduled_at?: Carbon|string|null, timezone?: string|null, batch_size?: int|null, batch_interval_minutes?: int|null}  $options
     */
    public function scheduleWithOptions(Campaign $campaign, array $options): Campaign
    {
        $mode = $options['mode'] ?? 'scheduled';
        $allowed = $mode === 'now' ? [CampaignStatus::Draft, CampaignStatus::Scheduled] : [CampaignStatus::Draft];

        if (! in_array($campaign->statusEnum(), $allowed, true) || $campaign->isArchived()) {
            throw new CampaignStateConflictException('Seules les campagnes en brouillon peuvent être programmées.');
        }

        // docs/45 §45.7.2 — an A/B test sends its whole sample at once and the
        // remainder after the pick: per-contact best time and batch spreading
        // would release the sample over hours and pick on partial data.
        if (in_array($mode, self::AB_TEST_EXCLUDED_MODES, true) && $campaign->ab_test_enabled) {
            throw ValidationException::withMessages([
                'mode' => ['L’envoi au meilleur moment et l’envoi par lots ne sont pas disponibles pour une campagne avec test A/B.'],
            ]);
        }

        $this->assertReadyToDispatch($campaign);

        $scheduledAt = isset($options['scheduled_at']) && $options['scheduled_at'] !== null
            ? Carbon::parse($options['scheduled_at'])->utc()
            : null;

        $attributes = [
            'timezone' => $options['timezone'] ?? $campaign->timezone ?? self::DEFAULT_TIMEZONE,
            'send_mode' => CampaignSendMode::fromApi($mode)->value,
            'send_time_optimized' => $mode === 'best_time',
        ];

        if ($mode === 'batches') {
            $attributes['batch_size'] = (int) $options['batch_size'];
            $attributes['batch_interval_minutes'] = (int) $options['batch_interval_minutes'];
        } else {
            $attributes['batch_size'] = null;
            $attributes['batch_interval_minutes'] = null;
        }

        $campaign->update($attributes);

        if ($mode !== 'now' && $scheduledAt !== null && $scheduledAt->isFuture()) {
            $this->transition($campaign, CampaignStatus::Scheduled);
            $campaign->update(['scheduled_at' => $scheduledAt]);

            DispatchCampaignJob::dispatch($campaign->id, $scheduledAt->toIso8601String())->delay($scheduledAt);

            return $campaign->fresh();
        }

        $this->transition($campaign, CampaignStatus::Running);
        $campaign->update(['scheduled_at' => null]);

        DispatchCampaignJob::dispatch($campaign->id);

        return $campaign->fresh();
    }

    /**
     * §15.1 draft --> running, §15.5 Send Immediately (`POST /send`, legacy
     * 422 on state).
     */
    public function sendNow(Campaign $campaign): Campaign
    {
        if (! in_array($campaign->statusEnum(), [CampaignStatus::Draft, CampaignStatus::Scheduled], true)) {
            throw new InvalidCampaignTransitionException($campaign->statusEnum(), CampaignStatus::Running);
        }

        return $this->scheduleWithOptions($campaign, ['mode' => 'now']);
    }

    /**
     * scheduled --> draft (docs/45 §45.4.2 `unschedule`).
     */
    public function unschedule(Campaign $campaign): Campaign
    {
        if ($campaign->status !== CampaignStatus::Scheduled->value) {
            throw new CampaignStateConflictException('Seules les campagnes programmées peuvent être déprogrammées.');
        }

        $this->transition($campaign, CampaignStatus::Draft);
        $campaign->update(['scheduled_at' => null]);

        return $campaign->fresh();
    }

    /**
     * §15.6 Pause — stops new batches from being released.
     */
    public function pause(Campaign $campaign): Campaign
    {
        $this->assertNotArchived($campaign);
        $this->transition($campaign, CampaignStatus::Paused);

        return $campaign->fresh();
    }

    /**
     * §15.6 Resume — valid from `paused` and (v2) `suspended`, which also
     * clears `suspended_reason`. Pending `campaign_recipients` rows are the
     * resumption set.
     */
    public function resume(Campaign $campaign): Campaign
    {
        $this->assertNotArchived($campaign);

        if (! in_array($campaign->statusEnum(), [CampaignStatus::Paused, CampaignStatus::Suspended], true)) {
            throw new InvalidCampaignTransitionException($campaign->statusEnum(), CampaignStatus::Running);
        }

        $this->assertApproved($campaign);

        $this->transition($campaign, CampaignStatus::Running);
        $campaign->update(['suspended_reason' => null]);

        DispatchCampaignJob::dispatch($campaign->id);

        return $campaign->fresh();
    }

    /**
     * System pause (docs/45 §45.4.2 `suspended`): quota exhausted, every SMTP
     * account failing, approval revoked.
     */
    public function suspend(Campaign $campaign, CampaignSuspendedReason $reason): Campaign
    {
        $this->transition($campaign, CampaignStatus::Suspended, $reason->value);
        $campaign->update(['suspended_reason' => $reason->value]);

        return $campaign->fresh();
    }

    /**
     * §15.6 Cancel — pending rows are simply never dispatched. A draft is
     * deleted, not cancelled (docs/45 §45.4.2 `available_actions`).
     */
    public function cancel(Campaign $campaign): Campaign
    {
        $this->assertNotArchived($campaign);

        if ($campaign->status === CampaignStatus::Draft->value) {
            throw new CampaignStateConflictException('Une campagne en brouillon ne peut pas être annulée.');
        }

        $this->transition($campaign, CampaignStatus::Cancelled);

        return $campaign->fresh();
    }

    /**
     * §15.4 Review Before Sending — records approval (`campaign.approved`
     * audit entry). Possible while the campaign can still be sent: draft, or
     * suspended (an edit of a suspended campaign withdraws its approval, which
     * {@see self::resume()} then requires again). Approving an already
     * approved campaign changes nothing.
     */
    public function approve(Campaign $campaign, User $approver): Campaign
    {
        $this->assertNotArchived($campaign);

        if (! in_array($campaign->status, [CampaignStatus::Draft->value, CampaignStatus::Suspended->value], true)) {
            throw new InvalidCampaignTransitionException($campaign->statusEnum(), CampaignStatus::Draft);
        }

        if (! $campaign->approval_required) {
            throw new CampaignStateConflictException('Cette campagne ne nécessite pas d’approbation.');
        }

        if ($campaign->approved_by !== null) {
            return $campaign->fresh();
        }

        DB::transaction(function () use ($campaign, $approver): void {
            $campaign->update(['approved_by' => $approver->id, 'approved_at' => now()]);

            $this->audit->record(self::AUDIT_APPROVED, $campaign, $approver, null, [
                'approved_by' => $approver->uuid,
                'approved_at' => $campaign->approved_at?->utc()->toIso8601String(),
            ]);
        });

        return $campaign->fresh();
    }

    public function archive(Campaign $campaign): Campaign
    {
        if ($campaign->isArchived() || ! $campaign->statusEnum()->isArchivable()) {
            throw new CampaignStateConflictException('Seules les campagnes envoyées, suspendues ou annulées peuvent être archivées.');
        }

        $campaign->update(['archived_at' => now()]);
        CampaignStatusChanged::dispatch($campaign->id, $campaign->status, 'archived', auth()->id());

        return $campaign->fresh();
    }

    public function unarchive(Campaign $campaign): Campaign
    {
        if (! $campaign->isArchived()) {
            throw new CampaignStateConflictException("Cette campagne n'est pas archivée.");
        }

        $campaign->update(['archived_at' => null]);
        CampaignStatusChanged::dispatch($campaign->id, 'archived', $campaign->status, auth()->id());

        return $campaign->fresh();
    }

    public function delete(Campaign $campaign): void
    {
        if (! in_array('delete', $this->availableActions($campaign), true)) {
            throw new CampaignStateConflictException('Cette campagne ne peut pas être supprimée dans son état actuel.');
        }

        $campaign->delete();
    }

    public function move(Campaign $campaign, ?int $folderId): Campaign
    {
        $campaign->update(['folder_id' => $folderId]);

        return $campaign->fresh();
    }

    /**
     * docs/45 §45.4.2 `duplicate` (and the legacy `clone` alias, §15.7):
     * audience + design + settings copied, status draft, schedule/approval/
     * stats reset.
     *
     * @param  array{name?: string|null, folder_id?: int|null}  $options  `folder_id` key present = override
     */
    public function duplicate(Campaign $campaign, User $createdBy, array $options = []): Campaign
    {
        return DB::transaction(function () use ($campaign, $createdBy, $options): Campaign {
            $copy = Campaign::query()->create([
                ...$campaign->only(Campaign::COPYABLE_COLUMNS),
                'name' => $options['name'] ?? 'Copie de '.$campaign->name,
                'folder_id' => array_key_exists('folder_id', $options) ? $options['folder_id'] : $campaign->folder_id,
                'status' => CampaignStatus::Draft->value,
                'scheduled_at' => null,
                'recurrence_rule' => null,
                'approved_by' => null,
                'approved_at' => null,
                'cloned_from_campaign_id' => $campaign->id,
                'created_by' => $createdBy->id,
            ]);

            $this->copyAudienceRows($campaign, $copy);
            app(AbTestService::class)->copyVariants($campaign, $copy);

            return $copy->fresh();
        });
    }

    /**
     * §15.7 — legacy alias of {@see duplicate()}.
     */
    public function clone(Campaign $campaign, User $createdBy): Campaign
    {
        return $this->duplicate($campaign, $createdBy);
    }

    /**
     * docs/45 §45.4.2 — child draft (`parent_campaign_id`) whose audience is
     * the parent's audience minus everyone in the parent's
     * `campaign_recipients` (applied by the resolver at resolution time, so
     * contacts added until the child is sent are included). An A/B parent
     * with a picked winner yields a classic child with the winner's content.
     */
    public function sendToNewContacts(Campaign $campaign, User $createdBy, ?string $name = null): Campaign
    {
        if ($campaign->status !== CampaignStatus::Completed->value) {
            throw new CampaignStateConflictException('Seules les campagnes envoyées peuvent être renvoyées aux nouveaux contacts.');
        }

        $abTests = app(AbTestService::class);
        // docs/45 §45.7.2 — once the A/B winner is picked, new contacts get the
        // winner as a classic campaign instead of a new test.
        $winner = $abTests->winnerCopyAttributes($campaign);

        return DB::transaction(function () use ($campaign, $createdBy, $name, $abTests, $winner): Campaign {
            $child = Campaign::query()->create([
                ...$campaign->only(Campaign::COPYABLE_COLUMNS),
                ...($winner ?? []),
                'name' => $name ?? $campaign->name.' — nouveaux contacts',
                'status' => CampaignStatus::Draft->value,
                'parent_campaign_id' => $campaign->id,
                'created_by' => $createdBy->id,
            ]);

            $this->copyAudienceRows($campaign, $child);
            if ($winner === null) {
                $abTests->copyVariants($campaign, $child);
            }

            return $child->fresh();
        });
    }

    // ---------------------------------------------------------------------
    // Test send
    // ---------------------------------------------------------------------

    /**
     * docs/45 §45.4.2 `test-send` — through the normal pipeline
     * (`Message` + `SendEmailJob`) with `messages.is_test = true`. Each
     * address needs a `recipients` row (`messages.recipient_id` is NOT NULL):
     * a soft-deleted contact is reused as is (never restored), and unknown
     * addresses are created with status `invalid` so they never become
     * marketing contacts — the audience resolver excludes `invalid`
     * (`RecipientSource` has no `test_send` case, so `source` stays `manual`).
     *
     * @param  list<string>  $emails
     * @return array{queued: int, message_ids: list<string>, test_sent_at: string}
     */
    public function testSend(Campaign $campaign, array $emails, ?Recipient $contact, ?User $actor, ?\App\Modules\Campaigns\Models\AbTestVariant $variant = null): array
    {
        if (blank($campaign->subject) || blank($campaign->html_body)) {
            throw new CampaignNotReadyException("La campagne doit avoir un objet et un contenu avant l'envoi d'un test.");
        }

        $messageIds = [];

        // A/B version asked for: its subject, preheader and content (message.variant_id stays null — no A/B stats).
        $abTests = app(\App\Modules\Campaigns\AbTesting\Services\AbTestService::class);
        $subject = $variant !== null ? $abTests->subjectFor($campaign, $variant) : (string) $campaign->subject;
        $html = $variant !== null ? $abTests->htmlWithPreviewFor($campaign, $variant) : $this->htmlWithPreheader($campaign);

        foreach (array_values(array_unique(array_map('strtolower', $emails))) as $email) {
            $recipient = Recipient::withTrashed()->firstOrCreate(
                ['email' => $email],
                ['source' => RecipientSource::Manual->value, 'status' => RecipientStatus::Invalid->value, 'custom_fields' => []],
            );
            $mergeWith = $contact ?? $recipient;

            $message = $this->rendering->createMessage([
                'campaign_id' => $campaign->id,
                'recipient_id' => $recipient->id,
                'status' => MessageStatus::Queued->value,
                'queued_at' => now(),
                'is_test' => true,
            ], $subject, $html, $recipient, $campaign, $mergeWith);

            SendEmailJob::dispatch($message->id);
            $messageIds[] = $message->uuid;
        }

        $sentAt = now();
        $campaign->update(['test_sent_at' => $sentAt]);

        CampaignTestSent::dispatch($campaign->id, $emails, $actor?->id);

        return [
            'queued' => count($messageIds),
            'message_ids' => $messageIds,
            'test_sent_at' => $sentAt->toIso8601ZuluString(),
        ];
    }

    // ---------------------------------------------------------------------
    // Dispatch
    // ---------------------------------------------------------------------

    /**
     * Entry point called by {@see DispatchCampaignJob} for one fan-out cycle:
     * materialises the audience into `campaign_recipients` on the first cycle
     * (docs/43 §43.6.1), then releases up to {@see BATCH_SIZE} *due* pending
     * rows (`scheduled_for` null or past — batches mode spreads rows over
     * time) as `Message` + `SendEmailJob`, re-checking suppression; then
     * re-enqueues itself (immediately, or delayed until the next batch is
     * due) or completes the campaign.
     */
    public function runDispatchCycle(Campaign $campaign): void
    {
        $campaign->refresh();

        if (! in_array($campaign->status, [CampaignStatus::Scheduled->value, CampaignStatus::Running->value], true)) {
            return;
        }

        if ($campaign->status === CampaignStatus::Scheduled->value) {
            $this->transition($campaign, CampaignStatus::Running);
        }

        if (! $campaign->isAudienceMaterialised()) {
            $this->audience->resolve($campaign);

            if ($campaign->send_time_optimized && app()->bound(self::BEST_SEND_TIME_SERVICE)) {
                app(self::BEST_SEND_TIME_SERVICE)->plan($campaign);
            }
        }

        $due = $this->duePendingRows($campaign)->with('recipient')->orderBy('id')->limit(self::BATCH_SIZE)->get();

        $suppressedEmails = $due->isEmpty() ? [] : SuppressionEntry::query()
            ->whereIn('email', $due->pluck('recipient.email')->filter())
            ->pluck('email')
            ->flip()
            ->all();

        foreach ($due as $row) {
            $recipient = $row->recipient;

            if ($recipient === null
                || $recipient->status === RecipientStatus::Suppressed->value
                || isset($suppressedEmails[$recipient->email])) {
                $row->update(['status' => CampaignRecipientStatus::Skipped->value, 'skip_reason' => 'suppressed']);

                continue;
            }

            $message = $this->dispatchToRecipient($campaign, $recipient);
            $row->update(['status' => CampaignRecipientStatus::Queued->value, 'message_id' => $message->id]);
        }

        $campaign->refresh();

        if ($campaign->status !== CampaignStatus::Running->value) {
            return;
        }

        $pending = $campaign->campaignRecipients()->where('status', CampaignRecipientStatus::Pending->value);

        if (! $pending->exists()) {
            $this->transition($campaign, CampaignStatus::Completed);
            $campaign->update(['sent_at' => $campaign->sent_at ?? now(), 'completed_at' => now()]);

            return;
        }

        if ($this->duePendingRows($campaign)->exists()) {
            DispatchCampaignJob::dispatch($campaign->id);

            return;
        }

        $nextDue = Carbon::parse($pending->min('scheduled_for'));
        DispatchCampaignJob::dispatch($campaign->id)->delay($nextDue);
    }

    /**
     * Send window (`business_hours_*`, docs/15 §15.5): the instant the next
     * dispatch cycle must wait for, or `null` when the campaign may release
     * messages now (no window, inside it, or nothing left to release so the
     * campaign can still complete).
     */
    public function sendWindowDeferral(Campaign $campaign): ?Carbon
    {
        $window = SendWindow::forCampaign($campaign);

        if ($window === null || $window->contains(now())) {
            return null;
        }

        if (! in_array($campaign->status, [CampaignStatus::Scheduled->value, CampaignStatus::Running->value], true)) {
            return null;
        }

        if ($campaign->status === CampaignStatus::Running->value
            && $campaign->isAudienceMaterialised()
            && ! $campaign->campaignRecipients()->where('status', CampaignRecipientStatus::Pending->value)->exists()) {
            return null;
        }

        return $window->nextOpening(now());
    }

    /**
     * Legacy helper (analytics `targeted`) — final audience without
     * materialisation.
     *
     * @return Collection<int, Recipient>
     */
    public function resolveTargetRecipients(Campaign $campaign): Collection
    {
        return $this->audience->recipients($campaign);
    }

    public function hasAudience(Campaign $campaign): bool
    {
        return $campaign->audiences()->where('mode', AudienceMode::Include->value)->exists()
            || $campaign->recipient_list_id !== null
            || $campaign->segment_id !== null;
    }

    /**
     * @return list<string>
     */
    public function availableActions(Campaign $campaign): array
    {
        $hasSent = $campaign->status === CampaignStatus::Cancelled->value
            && $campaign->deliveryMessages()->exists();

        return array_map(
            fn ($action) => $action->value,
            $campaign->statusEnum()->availableActions($campaign->isArchived(), $hasSent),
        );
    }

    public function forgetEstimate(Campaign $campaign): void
    {
        Cache::forget(AudienceResolver::estimateCacheKey($campaign));
    }

    // ---------------------------------------------------------------------
    // Internals
    // ---------------------------------------------------------------------

    /**
     * @return Builder<CampaignRecipient>
     */
    private function duePendingRows(Campaign $campaign)
    {
        return CampaignRecipient::query()
            ->where('campaign_id', $campaign->id)
            ->where('status', CampaignRecipientStatus::Pending->value)
            ->where(fn ($q) => $q->whereNull('scheduled_for')->orWhere('scheduled_for', '<=', now()));
    }

    private function dispatchToRecipient(Campaign $campaign, Recipient $recipient): Message
    {
        $message = $this->rendering->createMessage([
            'campaign_id' => $campaign->id,
            'recipient_id' => $recipient->id,
            'status' => MessageStatus::Queued->value,
            'queued_at' => now(),
        ], (string) $campaign->subject, $this->htmlWithPreheader($campaign), $recipient, $campaign);

        SendEmailJob::dispatch($message->id);

        return $message;
    }

    /** The campaign's preview text as the hidden inbox preheader (docs/43 §43.4.2 Subject card). */
    private function htmlWithPreheader(Campaign $campaign): string
    {
        return \App\Modules\Campaigns\AbTesting\Services\AbTestService::withPreheader((string) $campaign->html_body, $campaign->preview_text);
    }

    private function copyAudienceRows(Campaign $from, Campaign $to): void
    {
        $rows = $from->audiences()->get();

        if ($rows->isEmpty()) {
            $this->syncLegacyAudience($to);

            return;
        }

        foreach ($rows as $row) {
            CampaignAudience::query()->create([
                'campaign_id' => $to->id,
                'target_type' => $row->target_type,
                'target_id' => $row->target_id,
                'mode' => $row->mode,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function designFromTemplate(string $uuid): array
    {
        $template = Template::query()->where('uuid', $uuid)->first();

        if ($template === null) {
            throw new CampaignNotReadyException('Modèle introuvable.');
        }

        return [
            'template_id' => $template->id,
            'editor_type' => $template->getAttribute('editor_type') ?? CampaignEditorType::Html->value,
            'html_body' => $template->getAttribute('html_content'),
            'design_json' => $this->decodeJson($template->getAttribute('design_json')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function designFromCampaign(string $uuid): array
    {
        $source = Campaign::query()->where('uuid', $uuid)->first();

        if ($source === null) {
            throw new CampaignNotReadyException('Campagne source introuvable.');
        }

        return [
            'template_id' => $source->template_id,
            'editor_type' => $source->editor_type ?? CampaignEditorType::Html->value,
            'html_body' => $source->html_body,
            'design_json' => $source->design_json,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJson(mixed $value): ?array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }

    private function assertEditable(Campaign $campaign): void
    {
        if ($campaign->isArchived() || ! $campaign->statusEnum()->isEditable()) {
            throw new CampaignStateConflictException('Seules les campagnes en brouillon, programmées ou suspendues peuvent être modifiées.');
        }
    }

    private function assertNotArchived(Campaign $campaign): void
    {
        if ($campaign->isArchived()) {
            throw new CampaignStateConflictException('Cette campagne est archivée.');
        }
    }

    /**
     * Edits of a scheduled / suspended campaign must keep it sendable: the
     * checklist is re-asserted (422, rolled back by the caller's transaction).
     */
    private function assertStillReady(Campaign $campaign): void
    {
        if (in_array($campaign->status, [CampaignStatus::Scheduled->value, CampaignStatus::Suspended->value], true)) {
            // A suspended campaign keeps its place without approval; resume() asks for it.
            $this->assertReadyToDispatch($campaign->fresh(), $campaign->status === CampaignStatus::Scheduled->value);
        }
    }

    /**
     * @return list<string>
     */
    private function audienceSignature(Campaign $campaign): array
    {
        return $campaign->audiences()->get()
            ->map(fn (CampaignAudience $row): string => $row->target_type.':'.$row->target_id.':'.$row->mode)
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Approval-sensitive attributes dirty on the (filled, unsaved) campaign,
     * plus `approval_required` when it was just switched on.
     *
     * @return list<string>
     */
    private function approvalRelevantChanges(Campaign $campaign): array
    {
        $changed = array_values(array_intersect(array_keys($campaign->getDirty()), self::APPROVAL_SENSITIVE_ATTRIBUTES));

        if ($campaign->isDirty('approval_required') && $campaign->approval_required) {
            $changed[] = 'approval_required';
        }

        return $changed;
    }

    /**
     * Editing what a reviewer approved (sender, subject, content, audience)
     * withdraws the approval — otherwise the approved version and the sent
     * version could differ. A scheduled campaign that thereby needs approval
     * again goes back to draft (its delayed dispatch job is then stale and
     * does nothing); `campaign.approval_reset` is audited.
     *
     * @param  list<string>  $changed
     */
    public function resetApprovalAfterEdit(Campaign $campaign, array $changed): void
    {
        if ($changed === []) {
            return;
        }

        $hadApproval = $campaign->approved_by !== null;

        if ($hadApproval) {
            $previousApprover = $campaign->approvedBy?->uuid;
            $campaign->forceFill(['approved_by' => null, 'approved_at' => null])->save();

            if ($campaign->approval_required) {
                $this->audit->record(self::AUDIT_APPROVAL_RESET, $campaign, auth()->user(), [
                    'approved_by' => $previousApprover,
                ], [
                    'approved_by' => null,
                    'changed' => array_values(array_unique($changed)),
                ]);
            }
        }

        if ($campaign->approval_required
            && $campaign->approved_by === null
            && $campaign->status === CampaignStatus::Scheduled->value
            && ($hadApproval || in_array('approval_required', $changed, true))) {
            $this->transition($campaign, CampaignStatus::Draft);
            $campaign->update(['scheduled_at' => null]);
        }
    }

    private function assertApproved(Campaign $campaign): void
    {
        if ($campaign->approval_required && $campaign->approved_by === null) {
            throw new CampaignNotReadyException('Cette campagne doit être approuvée avant de pouvoir être envoyée.');
        }
    }

    /**
     * Audience edited while suspended: pending rows are dropped and the
     * materialisation marker reset, so the next dispatch cycle re-resolves
     * (already queued / sent / skipped rows are kept and never re-sent).
     */
    private function discardPendingMaterialisation(Campaign $campaign): void
    {
        $campaign->campaignRecipients()->where('status', CampaignRecipientStatus::Pending->value)->delete();
        $campaign->forceFill(['recipients_count' => 0])->save();
    }

    private function assertReadyToDispatch(Campaign $campaign, bool $requireApproval = true): void
    {
        if (! $this->hasAudience($campaign)) {
            throw new CampaignNotReadyException('La campagne doit cibler au moins une liste de destinataires ou un segment.');
        }

        if (blank($campaign->subject) || blank($campaign->html_body)) {
            throw new CampaignNotReadyException('La campagne doit avoir un objet et un contenu avant de pouvoir être envoyée.');
        }

        if ($campaign->smtp_strategy === CampaignSmtpStrategy::Pinned->value && $campaign->singleSmtpAccount === null) {
            throw new CampaignNotReadyException('Choisissez le compte SMTP unique à utiliser, ou repassez en rotation automatique.');
        }

        if ($requireApproval) {
            $this->assertApproved($campaign);
        }

        if (app()->bound(self::CHECKLIST_SERVICE)) {
            $failures = array_values((array) app(self::CHECKLIST_SERVICE)->blockingFailures($campaign));

            if (! $requireApproval) {
                $failures = array_values(array_diff($failures, ['approval']));
            }

            if ($failures !== []) {
                throw new CampaignChecklistBlockedException($failures);
            }
        }

        app(AbTestService::class)->assertSchedulable($campaign);
    }

    private function transition(Campaign $campaign, CampaignStatus $to, ?string $reason = null): void
    {
        $from = $campaign->statusEnum();

        if (! $from->canTransitionTo($to)) {
            throw new InvalidCampaignTransitionException($from, $to);
        }

        DB::transaction(function () use ($campaign, $to): void {
            $campaign->update(['status' => $to->value]);
        });

        CampaignStatusChanged::dispatch($campaign->id, $from->value, $to->value, auth()->id(), $reason);
    }
}
