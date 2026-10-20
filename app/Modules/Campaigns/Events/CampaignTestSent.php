<?php

namespace App\Modules\Campaigns\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * docs/45-brevo-api-contracts.md §45.9 — a test send was queued.
 */
class CampaignTestSent implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /**
     * @param  list<string>  $emails
     */
    public function __construct(
        public readonly int $campaignId,
        public readonly array $emails,
        public readonly ?int $actorId,
    ) {}
}
