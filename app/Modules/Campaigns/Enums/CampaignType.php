<?php

namespace App\Modules\Campaigns\Enums;

/**
 * docs/45-brevo-api-contracts.md §45.4.2 — `campaigns.type`.
 */
enum CampaignType: string
{
    case Classic = 'classic';
    case AbTest = 'ab_test';
}
