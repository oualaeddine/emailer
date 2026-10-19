<?php

namespace App\Modules\Campaigns\Enums;

/**
 * docs/45-brevo-api-contracts.md §45.8 Slot B — `campaign_audiences.target_type`.
 */
enum AudienceTargetType: string
{
    case List = 'list';
    case Segment = 'segment';
}
