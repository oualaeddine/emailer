<?php

namespace App\Domain\Enums;

/**
 * docs/26-rbac.md §26.2 — Roles Summary.
 * Fixed set of four roles for v1 (custom/dynamic roles are a roadmap
 * item, docs/34-roadmap.md §34.3) — the underlying `roles`/`permissions`
 * schema already supports more, but only these four are seeded.
 */
enum RoleName: string
{
    case Administrator = 'administrator';
    case MarketingManager = 'marketing_manager';
    case MarketingOperator = 'marketing_operator';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::Administrator => 'Administrateur',
            self::MarketingManager => 'Responsable marketing',
            self::MarketingOperator => 'Opérateur marketing',
            self::Viewer => 'Lecteur',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Administrator => "Accès complet, y compris les identifiants SMTP, la gestion des utilisateurs, les journaux d'audit et les paramètres.",
            self::MarketingManager => "Accès complet aux campagnes/modèles/destinataires/rapports ; approuve les campagnes soumises à validation ; ne gère ni le SMTP ni les utilisateurs.",
            self::MarketingOperator => "Composition/envoi/import au quotidien ; ne gère pas la bibliothèque de modèles, n'approuve pas les campagnes, ne gère ni le SMTP ni les utilisateurs.",
            self::Viewer => "Lecture seule sur les tableaux de bord, rapports, historique des e-mails et analyses de campagnes.",
        };
    }
}
