<?php

namespace App\Modules\Tracking\Events;

use App\Modules\Tracking\Services\TrackingEventRecorder;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * docs/45-brevo-api-contracts.md §45.9 — fired for every `message_events`
 * row written by {@see TrackingEventRecorder}
 * (opens, clicks, and delivery transitions recorded through
 * `Message::markStatus()`). Internal ids.
 */
class MessageEventRecorded implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $messageEventId,
        public readonly int $messageId,
        public readonly string $type,
        public readonly ?int $campaignId = null,
        public readonly ?int $recipientId = null,
        public readonly ?string $url = null,
        public readonly ?string $linkId = null,
        public readonly bool $isProxyOpen = false,
    ) {}
}
