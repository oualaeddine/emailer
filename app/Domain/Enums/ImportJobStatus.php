<?php

namespace App\Domain\Enums;

/**
 * docs/04-database-design.md §4.6, docs/14-import-export.md §14.2.
 */
enum ImportJobStatus: string
{
    case Pending = 'pending';
    case Validating = 'validating';
    case Processing = 'processing';
    case Completed = 'completed';
    case CompletedWithErrors = 'completed_with_errors';
    case Failed = 'failed';
}
