<?php

namespace App\Domain\Enums;

/**
 * docs/04-database-design.md §4.9 — `send_attempts.status`.
 */
enum SendAttemptStatus: string
{
    case Success = 'success';
    case Failed = 'failed';
    case ConnectionError = 'connection_error';
}
