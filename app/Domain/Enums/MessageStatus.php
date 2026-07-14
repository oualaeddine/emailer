<?php

namespace App\Domain\Enums;

/**
 * docs/04-database-design.md §4.10 — `messages.status`.
 */
enum MessageStatus: string
{
    case Queued = 'queued';
    case Sending = 'sending';
    case Accepted = 'accepted';
    case Delivered = 'delivered';
    case Opened = 'opened';
    case Clicked = 'clicked';
    case SoftBounced = 'soft_bounced';
    case HardBounced = 'hard_bounced';
    case Failed = 'failed';
    case Rejected = 'rejected';
    case SpamComplaint = 'spam_complaint';
    case Unsubscribed = 'unsubscribed';

    public function isTerminalOrInFlight(): bool
    {
        return $this !== self::Queued;
    }
}
