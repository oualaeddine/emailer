<?php

namespace App\Domain\Enums;

/**
 * docs/04-database-design.md §4.3.
 */
enum DraftStatus: string
{
    case Draft = 'draft';
    case ReadyToSend = 'ready_to_send';
}
