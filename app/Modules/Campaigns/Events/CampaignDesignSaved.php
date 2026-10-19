<?php

namespace App\Modules\Campaigns\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * docs/45-brevo-api-contracts.md §45.9 — `PUT design` / `design/copy`.
 */
class CampaignDesignSaved implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $campaignId,
        public readonly ?string $editorType,
    ) {}
}
