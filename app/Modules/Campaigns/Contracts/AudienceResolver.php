<?php

namespace App\Modules\Campaigns\Contracts;

use App\Modules\Campaigns\Data\AudienceEstimate;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Recipients\Models\Recipient;
use Illuminate\Support\Collection;

/**
 * docs/45-brevo-api-contracts.md §45.9 / docs/43 §43.6.1 — union of included
 * lists' and segments' members, minus excluded lists/segments, minus
 * suppression, deduplicated by recipient id.
 */
interface AudienceResolver
{
    /**
     * Materialises the audience into `campaign_recipients` (idempotent;
     * existing rows are kept) and returns the materialised row count.
     */
    public function resolve(Campaign $campaign): int;

    /**
     * @param  array{lists?: list<string>, exclusion_lists?: list<string>, segments?: list<string>, excluded_segments?: list<string>}|null  $audience
     *                                                                                                                                                 uuids; null = the campaign's saved audience
     */
    public function estimate(Campaign $campaign, ?array $audience = null): AudienceEstimate;

    /**
     * Final recipient ids (no materialisation).
     *
     * @return list<int>
     */
    public function recipientIds(Campaign $campaign): array;

    /**
     * Final recipients (no materialisation).
     *
     * @return Collection<int, Recipient>
     */
    public function recipients(Campaign $campaign): Collection;
}
