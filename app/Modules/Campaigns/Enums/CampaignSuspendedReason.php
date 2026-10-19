<?php

namespace App\Modules\Campaigns\Enums;

/**
 * docs/45-brevo-api-contracts.md §45.4.2 — `campaigns.suspended_reason`.
 */
enum CampaignSuspendedReason: string
{
    case QuotaExhausted = 'quota_exhausted';
    case SmtpUnavailable = 'smtp_unavailable';
    case ApprovalRevoked = 'approval_revoked';
    case Manual = 'manual';
}
