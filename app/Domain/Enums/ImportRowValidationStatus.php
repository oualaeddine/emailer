<?php

namespace App\Domain\Enums;

/**
 * docs/04-database-design.md §4.6.
 */
enum ImportRowValidationStatus: string
{
    case Valid = 'valid';
    case Invalid = 'invalid';
    case Duplicate = 'duplicate';
}
