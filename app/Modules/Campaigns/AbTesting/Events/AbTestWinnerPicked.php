<?php

namespace App\Modules\Campaigns\AbTesting\Events;

use App\Modules\Campaigns\AbTesting\Jobs\PickAbWinnerJob;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * docs/45-brevo-api-contracts.md §45.9 — fired once the winner of an A/B
 * test is set (`pickedBy` = `auto` from {@see PickAbWinnerJob},
 * `manual` from `POST /campaigns/{id}/ab-test/winner`). Internal ids.
 */
class AbTestWinnerPicked implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $campaignId,
        public readonly int $variantId,
        public readonly string $pickedBy,
    ) {}
}
