<?php

namespace App\Domain\Notifications;

/**
 * docs/41-notification-center.md §41.2 — Scope (reuses the existing event catalog).
 */
enum NotificationCategory: string
{
    case DeliveryInfrastructure = 'delivery_infrastructure';
    case CampaignActivity = 'campaign_activity';
    case ImportActivity = 'import_activity';
    case AccountSecurity = 'account_security';
}
