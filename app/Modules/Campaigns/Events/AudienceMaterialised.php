<?php

namespace App\Modules\Campaigns\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * docs/45-brevo-api-contracts.md §45.9 — `campaign_recipients` written.
 */
class AudienceMaterialised implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $campaignId,
        public readonly int $count,
    ) {}
}
