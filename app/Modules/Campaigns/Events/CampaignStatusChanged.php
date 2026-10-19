<?php

namespace App\Modules\Campaigns\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * docs/45-brevo-api-contracts.md §45.9 — fired on every campaign
 * transition, including suspend and archive/unarchive (`to` / `from` =
 * `archived` for those).
 */
class CampaignStatusChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $campaignId,
        public readonly string $from,
        public readonly string $to,
        public readonly ?int $actorId = null,
        public readonly ?string $reason = null,
    ) {}
}
