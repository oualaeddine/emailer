<?php

namespace App\Modules\Campaigns\Services;

use App\Domain\Enums\MessageStatus;
use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Exceptions\CampaignNotReadyException;
use App\Modules\Campaigns\Exceptions\InvalidCampaignTransitionException;
use App\Modules\Campaigns\Jobs\DispatchCampaignJob;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Composer\Services\MergeVariableResolver;
use App\Modules\DeliveryEngine\Jobs\SendEmailJob;
use App\Modules\Identity\Models\User;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Recipients\Services\SegmentEvaluationService;
use App\Modules\Suppression\Models\SuppressionEntry;
use App\Modules\Tracking\Models\Message;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * docs/15-campaign-management.md — Campaign Management module.
 *
 * The single authority for every Campaign lifecycle transition (§15.1);
 * `Campaign` rows are never saved directly outside this service so every
 * transition stays centrally enforced, mirroring the "thin caller of a
 * WorkflowEngine-shaped state machine" framing of §15.1 /
 * docs/37-workflow-engine.md without pulling in a generic engine — this is
 * a correct concrete state machine for exactly the Campaign lifecycle.
 */
class CampaignService
{
    /**
     * §15.4/§30.4 — the fan-out chunk size `DispatchCampaignJob` processes
     * per invocation before checking campaign status and re-enqueuing
     * itself, so pause/cancel take effect within one chunk rather than
     * requiring the whole campaign to finish dispatching first.
     */
    public const BATCH_SIZE = 500;

    public function __construct(
        private readonly MergeVariableResolver $merge,
        private readonly SegmentEvaluationService $segments,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $createdBy): Campaign
    {
        return Campaign::query()->create([
            ...$data,
            'status' => CampaignStatus::Draft->value,
            'created_by' => $createdBy->id,
        ]);
    }

    /**
     * §15.8 "Content" tab — edits only allowed while `draft`.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Campaign $campaign, array $data): Campaign
    {
        $this->assertStatus($campaign, CampaignStatus::Draft);

        $campaign->update($data);

        return $campaign->fresh();
    }

    /**
     * §15.1 draft --> scheduled, §15.3 Scheduling.
     */
    public function schedule(Campaign $campaign, Carbon $scheduledAt): Campaign
    {
        $this->assertReadyToDispatch($campaign);
        $this->transition($campaign, CampaignStatus::Scheduled);
        $campaign->update(['scheduled_at' => $scheduledAt]);

        DispatchCampaignJob::dispatch($campaign->id)->delay($scheduledAt);

        return $campaign->fresh();
    }

    /**
     * §15.1 draft --> running, §15.5 Send Immediately.
     */
    public function sendNow(Campaign $campaign): Campaign
    {
        $this->assertReadyToDispatch($campaign);
        $this->transition($campaign, CampaignStatus::Running);

        DispatchCampaignJob::dispatch($campaign->id);

        return $campaign->fresh();
    }

    /**
     * §15.6 Pause — stops new batches from being *released*; batches
     * already picked up by a worker complete normally (`DispatchCampaignJob`
     * checks the campaign's status before releasing the next batch, not
     * mid-batch).
     */
    public function pause(Campaign $campaign): Campaign
    {
        $this->transition($campaign, CampaignStatus::Paused);

        return $campaign->fresh();
    }

    /**
     * §15.6 Resume — re-enables dispatch from where it left off: recipients
     * that do not yet have a `messages` row for this campaign are the
     * resumption set (see {@see resolvePendingRecipients()}).
     *
     * Explicitly requires the *current* status to be `paused` rather than
     * just checking the transition graph allows `-> running`: the graph
     * also allows `scheduled -> running` (§15.1, the dispatcher-triggered
     * transition when the scheduled time is reached), but that is not a
     * user-initiated "resume" — only a paused campaign can be resumed.
     */
    public function resume(Campaign $campaign): Campaign
    {
        $this->assertStatus($campaign, CampaignStatus::Paused);
        $this->transition($campaign, CampaignStatus::Running);

        DispatchCampaignJob::dispatch($campaign->id);

        return $campaign->fresh();
    }

    /**
     * §15.6 Cancel — any unsent recipients are simply never dispatched
     * (there is no pre-created `messages` row for them to flip to
     * failed/cancelled, since this module only creates a `Message` at the
     * moment a `SendEmailJob` is actually dispatched for a recipient);
     * already-sent messages are untouched (cancellation never un-sends).
     */
    public function cancel(Campaign $campaign): Campaign
    {
        $this->transition($campaign, CampaignStatus::Cancelled);

        return $campaign->fresh();
    }

    /**
     * §15.4 Review Before Sending — records approval; does not itself
     * change status (approval is a precondition checked by
     * {@see assertReadyToDispatch()}, not a transition).
     */
    public function approve(Campaign $campaign, User $approver): Campaign
    {
        $this->assertStatus($campaign, CampaignStatus::Draft);

        $campaign->update(['approved_by' => $approver->id, 'approved_at' => now()]);

        return $campaign->fresh();
    }

    /**
     * §15.7 Campaign Cloning — copies name (suffixed), template/content,
     * audience, and sending configuration into a new `draft`; scheduling
     * fields are reset (never copy a stale `scheduled_at`).
     */
    public function clone(Campaign $campaign, User $createdBy): Campaign
    {
        return Campaign::query()->create([
            'name' => 'Copie de '.$campaign->name,
            'template_id' => $campaign->template_id,
            'subject' => $campaign->subject,
            'html_body' => $campaign->html_body,
            'recipient_list_id' => $campaign->recipient_list_id,
            'segment_id' => $campaign->segment_id,
            'status' => CampaignStatus::Draft->value,
            'smtp_strategy' => $campaign->smtp_strategy,
            'single_smtp_account_id' => $campaign->single_smtp_account_id,
            'send_mode' => $campaign->send_mode,
            'scheduled_at' => null,
            'recurrence_rule' => null,
            'business_hours_only' => $campaign->business_hours_only,
            'business_hours_start' => $campaign->business_hours_start,
            'business_hours_end' => $campaign->business_hours_end,
            'business_days' => $campaign->business_days,
            'approval_required' => $campaign->approval_required,
            'approved_by' => null,
            'approved_at' => null,
            'tracking_enabled' => $campaign->tracking_enabled,
            'cloned_from_campaign_id' => $campaign->id,
            'created_by' => $createdBy->id,
        ]);
    }

    /**
     * Entry point called by {@see DispatchCampaignJob} for one fan-out
     * cycle: resolves the target audience (list or segment, always
     * excluding suppressed recipients per docs/23-suppression-list.md
     * §23.6), diffs out recipients already dispatched for this campaign
     * (the resumption set, §15.6), creates a `Message` + dispatches
     * `SendEmailJob` for up to {@see BATCH_SIZE} of them — exactly the
     * `ComposerSendService::send()` pattern, but per-recipient and with
     * `campaign_id` instead of `draft_id` (§WP-20 spec) — then either
     * re-enqueues itself (more remain and the campaign is still `running`)
     * or marks the campaign `completed`.
     *
     * Per-message suppression is also re-checked inside `SendEmailJob`
     * itself (docs/17-delivery-engine.md §17.3) — the pre-filter here is
     * belt-and-suspenders for recipients that were already suppressed at
     * fan-out time, not a replacement for that authoritative check.
     */
    public function runDispatchCycle(Campaign $campaign): void
    {
        $campaign->refresh();

        if (! in_array($campaign->status, [CampaignStatus::Scheduled->value, CampaignStatus::Running->value], true)) {
            // Paused/cancelled/completed between enqueue and execution —
            // nothing to do (§15.6: the dispatcher checks status before
            // releasing the next batch).
            return;
        }

        if ($campaign->status === CampaignStatus::Scheduled->value) {
            $this->transition($campaign, CampaignStatus::Running);
        }

        $pending = $this->resolvePendingRecipients($campaign);
        $batch = $pending->take(self::BATCH_SIZE);

        foreach ($batch as $recipient) {
            $this->dispatchToRecipient($campaign, $recipient);
        }

        $campaign->refresh();

        if ($campaign->status !== CampaignStatus::Running->value) {
            // Paused/cancelled mid-batch — do not release the next batch.
            return;
        }

        if ($pending->count() > $batch->count()) {
            DispatchCampaignJob::dispatch($campaign->id);

            return;
        }

        $this->transition($campaign, CampaignStatus::Completed);
        $campaign->update(['sent_at' => now()]);
    }

    /**
     * §15.2 step 3 — resolves the full (non-suppressed) target audience for
     * a campaign, from whichever of `recipient_list_id`/`segment_id` is set.
     *
     * @return Collection<int, Recipient>
     */
    public function resolveTargetRecipients(Campaign $campaign): Collection
    {
        if ($campaign->segment_id !== null) {
            // SegmentEvaluationService::compile() already excludes suppressed
            // recipients unconditionally (docs/13-recipient-management.md
            // §13.7, docs/23-suppression-list.md §23.6).
            return $this->segments->evaluate($campaign->segment);
        }

        $recipients = $campaign->recipientList?->recipients()->get() ?? new Collection();

        if ($recipients->isEmpty()) {
            return $recipients;
        }

        $suppressedEmails = SuppressionEntry::query()
            ->whereIn('email', $recipients->pluck('email'))
            ->pluck('email')
            ->all();

        return $recipients->reject(fn (Recipient $r): bool => in_array($r->email, $suppressedEmails, true))->values();
    }

    /**
     * Recipients in the target audience with no `messages` row for this
     * campaign yet — the resumption set (§15.6).
     *
     * @return Collection<int, Recipient>
     */
    private function resolvePendingRecipients(Campaign $campaign): Collection
    {
        $recipients = $this->resolveTargetRecipients($campaign);

        if ($recipients->isEmpty()) {
            return $recipients;
        }

        $alreadyDispatchedRecipientIds = Message::query()
            ->where('campaign_id', $campaign->id)
            ->whereIn('recipient_id', $recipients->pluck('id'))
            ->pluck('recipient_id')
            ->all();

        return $recipients
            ->reject(fn (Recipient $r): bool => in_array($r->id, $alreadyDispatchedRecipientIds, true))
            ->values();
    }

    /**
     * Mirrors {@see \App\Modules\DeliveryEngine\Services\ComposerSendService::send()}
     * exactly, but stamps `campaign_id` instead of `draft_id`.
     */
    private function dispatchToRecipient(Campaign $campaign, Recipient $recipient): Message
    {
        $message = Message::query()->create([
            'campaign_id' => $campaign->id,
            'recipient_id' => $recipient->id,
            'subject' => $this->merge->resolve((string) $campaign->subject, $recipient),
            'html_body' => $this->merge->resolve((string) $campaign->html_body, $recipient),
            'status' => MessageStatus::Queued->value,
            'queued_at' => now(),
        ]);

        SendEmailJob::dispatch($message->id);

        return $message;
    }

    private function assertReadyToDispatch(Campaign $campaign): void
    {
        $this->assertStatus($campaign, CampaignStatus::Draft);

        if ($campaign->recipient_list_id === null && $campaign->segment_id === null) {
            throw new CampaignNotReadyException('La campagne doit cibler une liste de destinataires ou un segment.');
        }

        if (blank($campaign->subject) || blank($campaign->html_body)) {
            throw new CampaignNotReadyException('La campagne doit avoir un objet et un contenu avant de pouvoir être envoyée.');
        }

        if ($campaign->approval_required && $campaign->approved_by === null) {
            throw new CampaignNotReadyException('Cette campagne doit être approuvée avant de pouvoir être envoyée.');
        }
    }

    private function assertStatus(Campaign $campaign, CampaignStatus $expected): void
    {
        if ($campaign->status !== $expected->value) {
            throw new InvalidCampaignTransitionException(CampaignStatus::from($campaign->status), $expected);
        }
    }

    private function transition(Campaign $campaign, CampaignStatus $to): void
    {
        $from = CampaignStatus::from($campaign->status);

        if (! $from->canTransitionTo($to)) {
            throw new InvalidCampaignTransitionException($from, $to);
        }

        DB::transaction(function () use ($campaign, $to): void {
            $campaign->update(['status' => $to->value]);
        });
    }
}
