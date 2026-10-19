<?php

namespace App\Modules\Campaigns\Enums;

/**
 * docs/45-brevo-api-contracts.md §45.8 Slot B — `campaign_recipients.status`.
 */
enum CampaignRecipientStatus: string
{
    case Pending = 'pending';
    case Queued = 'queued';
    case Sent = 'sent';
    case Skipped = 'skipped';
}
