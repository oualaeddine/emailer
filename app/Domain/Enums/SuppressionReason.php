<?php

namespace App\Domain\Enums;

/**
 * docs/04-database-design.md §4.12, docs/23-suppression-list.md §23.2.
 */
enum SuppressionReason: string
{
    case HardBounce = 'hard_bounce';
    case SpamComplaint = 'spam_complaint';
    case ManualUnsubscribe = 'manual_unsubscribe';
    case GlobalUnsubscribe = 'global_unsubscribe';
    case InvalidAddress = 'invalid_address';
    case ManualBlock = 'manual_block';
}
