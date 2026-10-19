<?php

namespace App\Modules\Campaigns\Services\Checklist;

use App\Modules\Campaigns\Models\Campaign;

/**
 * docs/45-brevo-api-contracts.md §45.9 — pre-send checklist service contract.
 */
interface CampaignChecklistContract
{
    public function evaluate(Campaign $campaign): CampaignChecklist;

    /**
     * @return list<string> keys of the items whose status is `error`
     */
    public function blockingFailures(Campaign $campaign): array;
}
