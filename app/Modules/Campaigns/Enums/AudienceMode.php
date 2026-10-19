<?php

namespace App\Modules\Campaigns\Enums;

/**
 * docs/45-brevo-api-contracts.md §45.8 Slot B — `campaign_audiences.mode`.
 */
enum AudienceMode: string
{
    case Include = 'include';
    case Exclude = 'exclude';
}
