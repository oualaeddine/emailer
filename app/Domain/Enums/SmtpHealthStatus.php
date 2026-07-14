<?php

namespace App\Domain\Enums;

/**
 * docs/04-database-design.md §4.9, docs/18-smtp-management.md §18.5.
 */
enum SmtpHealthStatus: string
{
    case Healthy = 'healthy';
    case Degraded = 'degraded';
    case Unhealthy = 'unhealthy';
    case Disabled = 'disabled';

    public function label(): string
    {
        return match ($this) {
            self::Healthy => 'Sain',
            self::Degraded => 'Dégradé',
            self::Unhealthy => 'Défaillant',
            self::Disabled => 'Désactivé',
        };
    }
}
