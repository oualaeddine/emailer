<?php

namespace App\Modules\Campaigns\Enums;

/**
 * docs/45-brevo-api-contracts.md §45.4.2 — read-only Brevo status mapping
 * (`brevo_status`, TS `CampaignBrevoStatus`). `in_review` and `cancelling`
 * exist for Brevo parity only.
 */
enum CampaignBrevoStatus: string
{
    case Draft = 'draft';
    case Queued = 'queued';
    case InReview = 'in_review';
    case InProcess = 'in_process';
    case Sent = 'sent';
    case Suspended = 'suspended';
    case Cancelling = 'cancelling';
    case Cancelled = 'cancelled';
    case Archive = 'archive';
}
