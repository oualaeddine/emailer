<?php

namespace App\Modules\Campaigns\Enums;

/**
 * docs/15-campaign-management.md §15.3 — Scheduling & Recurrence.
 */
enum CampaignSendMode: string
{
    case Immediate = 'immediate';
    case Scheduled = 'scheduled';
    case Recurring = 'recurring';
}
