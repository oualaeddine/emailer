<?php

namespace App\Domain\Enums;

/**
 * docs/04-database-design.md §4.5.
 */
enum RecipientStatus: string
{
    case Active = 'active';
    case Suppressed = 'suppressed';
    case Invalid = 'invalid';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Actif',
            self::Suppressed => 'Supprimé',
            self::Invalid => 'Invalide',
        };
    }
}
