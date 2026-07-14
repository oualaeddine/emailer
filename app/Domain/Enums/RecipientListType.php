<?php

namespace App\Domain\Enums;

/**
 * docs/04-database-design.md §4.5, docs/13-recipient-management.md §13.7.
 */
enum RecipientListType: string
{
    case Static = 'static';
    case DynamicSegment = 'dynamic_segment';
}
