<?php

namespace App\Modules\Campaigns\AbTesting\Services;

use App\Domain\Enums\MessageStatus;
use App\Domain\Enums\RecipientStatus;
use App\Modules\Campaigns\AbTesting\Jobs\PickAbWinnerJob;
use App\Modules\Campaigns\Contracts\AudienceResolver;
use App\Modules\Campaigns\Enums\CampaignRecipientStatus;
use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Events\CampaignStatusChanged;
use App\Modules\Campaigns\Exceptions\InvalidCampaignTransitionException;
use App\Modules\Campaigns\Jobs\DispatchCampaignJob;
use App\Modules\Campaigns\Models\AbTestVariant;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\CampaignRecipient;
use App\Modules\Campaigns\Services\CampaignService;
use App\Modules\DeliveryEngine\Jobs\SendEmailJob;
use App\Modules\DeliveryEngine\Rendering\RenderingPipeline;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Suppression\Models\SuppressionEntry;
use App\Modules\Tracking\Models\Message;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * docs/43 §43.6.2 — split dispatch of an A/B test campaign, run by
 * {@see DispatchCampaignJob} instead of `CampaignService::runDispatchCycle()`
 * when `ab_test_enabled`.
 *
 * Same fan-out rules as the classic cycle (materialise once, release up to
 * {@see CampaignService::BATCH_SIZE} due pending rows, re-check suppression,
 * re-enqueue), plus:
 *
 * 1. test phase (`winner_variant_id` null): only the sample rows
 *    (`campaign_recipients.variant_id` set by {@see VariantAssigner}) are
 *    released, each with its version's subject / html (`messages.variant_id`
 *    set). `sent_at` marks the start of the test. When the last sample row
 *    is released, {@see PickAbWinnerJob} is scheduled at
 *    `sent_at + test_duration_minutes`; the remainder waits (status stays
 *    `running`).
 * 2. after the pick: the remainder (`variant_id` null) is released with the
 *    winner's subject / html, then the campaign completes.
 */
class AbTestDispatcher
{
    public function __construct(
        private readonly RenderingPipeline $rendering,
        private readonly AudienceResolver $audience,
        private readonly AbTestService $abTests,
    ) {}

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
            $campaign->refresh();
        }

        $variants = $campaign->abTestVariants()->get()->keyBy('id');
        $winner = $campaign->winner_variant_id !== null ? $variants->get($campaign->winner_variant_id) : null;
        // No sample rows (versions missing at materialisation): plain send of the campaign content.
        $testing = $campaign->winner_variant_id === null
            && $campaign->campaignRecipients()->whereNotNull('variant_id')->exists();

        $due = $this->duePendingRows($campaign, $testing)->with('recipient')->orderBy('id')->limit(CampaignService::BATCH_SIZE)->get();

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

            $variant = $row->variant_id !== null ? $variants->get($row->variant_id) : $winner;
            $message = $this->dispatchToRecipient($campaign, $recipient, $variant, $row->variant_id);
            $row->update(['status' => CampaignRecipientStatus::Queued->value, 'message_id' => $message->id]);
        }

        if ($due->isNotEmpty() && $campaign->sent_at === null) {
            $campaign->update(['sent_at' => now()]);
        }

        $campaign->refresh();

        if ($campaign->status !== CampaignStatus::Running->value) {
            return;
        }

        if ($testing) {
            $this->continueTestPhase($campaign, $due->isNotEmpty());

            return;
        }

        $pending = $campaign->campaignRecipients()->where('status', CampaignRecipientStatus::Pending->value);

        if (! $pending->exists()) {
            $this->transition($campaign, CampaignStatus::Completed);
            $campaign->update(['sent_at' => $campaign->sent_at ?? now(), 'completed_at' => now()]);

            return;
        }

        $this->reenqueue($campaign, false);
    }

    /**
     * Sample still pending → next chunk (now or when the next batch is due);
     * sample fully released by this cycle → schedule the winner pick.
     */
    private function continueTestPhase(Campaign $campaign, bool $releasedThisCycle): void
    {
        $samplePending = $this->pendingRows($campaign, true)->exists();

        if ($samplePending) {
            $this->reenqueue($campaign, true);

            return;
        }

        if ($releasedThisCycle) {
            $dueAt = $this->abTests->winnerDueAt($campaign);
            PickAbWinnerJob::dispatch($campaign->id)->delay($dueAt->isFuture() ? $dueAt : now());
        }
    }

    private function reenqueue(Campaign $campaign, bool $sampleOnly): void
    {
        if ($this->duePendingRows($campaign, $sampleOnly)->exists()) {
            DispatchCampaignJob::dispatch($campaign->id);

            return;
        }

        $nextDue = $this->pendingRows($campaign, $sampleOnly)->min('scheduled_for');

        if ($nextDue !== null) {
            DispatchCampaignJob::dispatch($campaign->id)->delay(Carbon::parse($nextDue));
        }
    }

    /**
     * @return Builder<CampaignRecipient>
     */
    private function pendingRows(Campaign $campaign, bool $sampleOnly)
    {
        return CampaignRecipient::query()
            ->where('campaign_id', $campaign->id)
            ->where('status', CampaignRecipientStatus::Pending->value)
            ->when($sampleOnly, fn ($q) => $q->whereNotNull('variant_id'));
    }

    /**
     * @return Builder<CampaignRecipient>
     */
    private function duePendingRows(Campaign $campaign, bool $sampleOnly)
    {
        return $this->pendingRows($campaign, $sampleOnly)
            ->where(fn ($q) => $q->whereNull('scheduled_for')->orWhere('scheduled_for', '<=', now()));
    }

    private function dispatchToRecipient(Campaign $campaign, Recipient $recipient, ?AbTestVariant $variant, ?int $variantId): Message
    {
        $message = $this->rendering->createMessage([
            'campaign_id' => $campaign->id,
            'recipient_id' => $recipient->id,
            'status' => MessageStatus::Queued->value,
            'queued_at' => now(),
            'variant_id' => $variantId,
        ], $this->abTests->subjectFor($campaign, $variant), $this->abTests->htmlFor($campaign, $variant), $recipient, $campaign);

        SendEmailJob::dispatch($message->id);

        return $message;
    }

    /**
     * Mirrors `CampaignService::transition()` (every transition fires
     * {@see CampaignStatusChanged}).
     */
    private function transition(Campaign $campaign, CampaignStatus $to): void
    {
        $from = $campaign->statusEnum();

        if (! $from->canTransitionTo($to)) {
            throw new InvalidCampaignTransitionException($from, $to);
        }

        $campaign->update(['status' => $to->value]);

        CampaignStatusChanged::dispatch($campaign->id, $from->value, $to->value, auth()->id());
    }
}
