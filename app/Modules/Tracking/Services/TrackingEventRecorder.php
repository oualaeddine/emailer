<?php

namespace App\Modules\Tracking\Services;

use App\Domain\Enums\MessageStatus;
use App\Modules\Tracking\Models\Message;

/**
 * docs/21-open-click-tracking.md §21.1-21.3 — the counter/status-transition
 * side effects of a pixel hit or click redirect.
 *
 * §21.3 "Unique Recipient Tracking": because the pixel/redirect URL is
 * keyed 1:1 to a single `messages` row (a specific recipient+send), every
 * hit is inherently attributable to that one recipient — there is no
 * separate per-recipient de-duplication to do here. What §21.1/§21.2 do
 * specify precisely is the *count vs. timestamp* semantics: every open
 * increments `open_count`, but `opened_at` is stamped only the first time
 * ("sets ... opened_at if not already set"); same shape for
 * click_count/clicked_at.
 *
 * §21.1 also requires status transitions to "never downgrade a later
 * status like `clicked` back to `opened`". `Message::markStatus()`
 * (App\Modules\Tracking\Models\Message, WP-12, not modified by this WP)
 * always overwrites `status` unconditionally, so it is deliberately not
 * used here — this service only ever advances status forward along the
 * positive delivery funnel (queued -> sending -> accepted -> delivered ->
 * opened -> clicked) and never touches a message already in a terminal or
 * bounce/failure state (soft_bounced, hard_bounced, failed, rejected,
 * spam_complaint, unsubscribed) or already further along the funnel.
 */
class TrackingEventRecorder
{
    /**
     * @var array<string, int>
     */
    private const FUNNEL_RANK = [
        'queued' => 0,
        'sending' => 1,
        'accepted' => 2,
        'delivered' => 3,
        'opened' => 4,
        'clicked' => 5,
    ];

    public function recordOpen(Message $message): void
    {
        $message->open_count = $message->open_count + 1;

        if ($message->opened_at === null) {
            $message->opened_at = now();
        }

        $this->advanceStatusIfEarlier($message, MessageStatus::Opened);

        $message->save();
    }

    public function recordClick(Message $message): void
    {
        $message->click_count = $message->click_count + 1;

        if ($message->clicked_at === null) {
            $message->clicked_at = now();
        }

        $this->advanceStatusIfEarlier($message, MessageStatus::Clicked);

        $message->save();
    }

    private function advanceStatusIfEarlier(Message $message, MessageStatus $target): void
    {
        $currentRank = self::FUNNEL_RANK[$message->status] ?? null;

        // Terminal/bounce/failure statuses (not present in FUNNEL_RANK) are
        // never overwritten by an open/click side effect.
        if ($currentRank === null) {
            return;
        }

        if ($currentRank < self::FUNNEL_RANK[$target->value]) {
            $message->status = $target->value;
        }
    }
}
