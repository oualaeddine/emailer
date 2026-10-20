<?php

namespace App\Modules\DeliveryEngine\Services;

use App\Domain\Enums\MessageStatus;
use App\Modules\Composer\Models\Draft;
use App\Modules\DeliveryEngine\Jobs\SendEmailJob;
use App\Modules\DeliveryEngine\Rendering\RenderingPipeline;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Tracking\Models\Message;

/**
 * docs/11-email-composer.md, docs/17-delivery-engine.md §17.3 — bridges
 * the Composer module to the Delivery Engine for a single transactional
 * send (campaign-scale dispatch is the Campaigns module's concern).
 */
class ComposerSendService
{
    public function __construct(private readonly RenderingPipeline $rendering)
    {
    }

    public function send(Draft $draft, Recipient $recipient): Message
    {
        $message = $this->rendering->createMessage([
            'draft_id' => $draft->id,
            'recipient_id' => $recipient->id,
            'status' => MessageStatus::Queued->value,
            'queued_at' => now(),
        ], (string) $draft->subject, (string) $draft->html_body, $recipient, draft: $draft);

        SendEmailJob::dispatch($message->id);

        return $message;
    }
}
