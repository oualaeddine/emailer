<?php

namespace App\Domain\Enums;

/**
 * docs/04-database-design.md §4.9.
 */
enum SmtpEncryption: string
{
    case None = 'none';
    case Ssl = 'ssl';
    case Tls = 'tls';
}
