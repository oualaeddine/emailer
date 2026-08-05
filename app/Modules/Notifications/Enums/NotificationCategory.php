<?php

namespace App\Modules\Notifications\Enums;

/**
 * docs/41-notification-center.md §41.2 — Notification Category taxonomy
 * (the "Notification Category" column of the trigger table): every
 * existing event listener's dispatched notification is bucketed into one
 * of these four categories. §41.4 — this is also the first axis of the
 * per-user preference matrix in `notification_preferences`.
 */
enum NotificationCategory: string
{
    case DeliveryInfrastructure = 'delivery_infrastructure';
    case CampaignActivity = 'campaign_activity';
    case ImportActivity = 'import_activity';
    case AccountSecurity = 'account_security';
}
