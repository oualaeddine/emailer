<?php

namespace App\Modules\Campaigns\Enums;

/**
 * docs/15-campaign-management.md §15.2 step 4 — "Sending Configuration":
 * `auto_rotate` (Rotation Engine picks the account per message, see
 * docs/17-delivery-engine.md/docs/18-smtp-rotation.md) vs. pinning the
 * whole campaign to a single `smtp_accounts` row.
 */
enum CampaignSmtpStrategy: string
{
    case AutoRotate = 'auto_rotate';
    case Pinned = 'pinned';
}
