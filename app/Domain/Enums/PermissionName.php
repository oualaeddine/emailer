<?php

namespace App\Domain\Enums;

/**
 * docs/26-rbac.md §26.3 — Full Permission Matrix.
 * Every permission key referenced anywhere in the documentation set is
 * enumerated here so no module can introduce an undocumented permission
 * string by typo — this is the single source of truth the
 * `RolePermissionSeeder` and every Policy/route gate must use.
 */
enum PermissionName: string
{
    case DashboardView = 'dashboard.view';

    case MailboxViewOwn = 'mailbox.view_own';
    case MailboxViewAll = 'mailbox.view_all';

    case ComposerCompose = 'composer.compose';
    case ComposerSend = 'composer.send';

    case TemplatesView = 'templates.view';
    case TemplatesManage = 'templates.manage';

    case RecipientsView = 'recipients.view';
    case RecipientsManage = 'recipients.manage';
    case RecipientsImport = 'recipients.import';
    case RecipientsVerify = 'recipients.verify';
    case RecipientsAnnotate = 'recipients.annotate';

    case SegmentsManage = 'segments.manage';

    case CampaignsView = 'campaigns.view';
    case CampaignsCreate = 'campaigns.create';
    case CampaignsSend = 'campaigns.send';
    case CampaignsSendAny = 'campaigns.send_any';
    case CampaignsApprove = 'campaigns.approve';
    case CampaignsCancelPause = 'campaigns.cancel_pause';

    case SmtpView = 'smtp.view';
    case SmtpManageCredentials = 'smtp.manage_credentials';
    case SmtpManageQuotasRotation = 'smtp.manage_quotas_rotation';
    case SmtpTest = 'smtp.test';

    case QueuesView = 'queues.view';

    case ReportingView = 'reporting.view';
    case ReportingExport = 'reporting.export';

    case SuppressionView = 'suppression.view';
    case SuppressionManage = 'suppression.manage';

    case AuditView = 'audit.view';
    case AuditExport = 'audit.export';

    case UsersManage = 'users.manage';

    case SettingsManage = 'settings.manage';
    case SettingsBrandingOnly = 'settings.branding_only';

    /**
     * The `{module}.{action}` prefix, used to populate `permissions.module`.
     */
    public function module(): string
    {
        return explode('.', $this->value)[0];
    }

    public function label(): string
    {
        return match ($this) {
            self::DashboardView => 'Voir le tableau de bord',
            self::MailboxViewOwn => 'Voir sa propre messagerie',
            self::MailboxViewAll => "Voir la messagerie de toute l'organisation",
            self::ComposerCompose => 'Rédiger un e-mail',
            self::ComposerSend => 'Envoyer un e-mail',
            self::TemplatesView => 'Voir les modèles',
            self::TemplatesManage => 'Gérer les modèles',
            self::RecipientsView => 'Voir les destinataires',
            self::RecipientsManage => 'Gérer les destinataires',
            self::RecipientsImport => 'Importer des destinataires',
            self::RecipientsVerify => 'Vérifier une adresse e-mail',
            self::RecipientsAnnotate => 'Ajouter des notes aux destinataires',
            self::SegmentsManage => 'Gérer les segments',
            self::CampaignsView => 'Voir les campagnes',
            self::CampaignsCreate => 'Créer des campagnes',
            self::CampaignsSend => 'Envoyer ses propres campagnes',
            self::CampaignsSendAny => "Envoyer n'importe quelle campagne",
            self::CampaignsApprove => 'Approuver les campagnes',
            self::CampaignsCancelPause => 'Annuler/mettre en pause une campagne',
            self::SmtpView => 'Voir les comptes SMTP',
            self::SmtpManageCredentials => 'Gérer les identifiants SMTP',
            self::SmtpManageQuotasRotation => 'Gérer les quotas et la rotation SMTP',
            self::SmtpTest => 'Tester un compte SMTP',
            self::QueuesView => 'Voir le suivi des files d\'attente',
            self::ReportingView => 'Voir les rapports',
            self::ReportingExport => 'Exporter les rapports',
            self::SuppressionView => 'Voir la liste de suppression',
            self::SuppressionManage => 'Gérer la liste de suppression',
            self::AuditView => "Voir les journaux d'audit",
            self::AuditExport => "Exporter les journaux d'audit",
            self::UsersManage => 'Gérer les utilisateurs',
            self::SettingsManage => 'Gérer les paramètres',
            self::SettingsBrandingOnly => "Gérer l'identité visuelle uniquement",
        };
    }
}
