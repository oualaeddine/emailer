<?php

namespace App\Modules\Campaigns\Enums;

use App\Modules\Campaigns\Services\CampaignService;

/**
 * docs/15-campaign-management.md §15.1 — Campaign Lifecycle (State Machine),
 * extended by docs/43-brevo-parity-plan.md §43.4.1 / docs/45 §45.4.2 (doc 43
 * wins where they differ):
 *
 * ```
 * draft --> scheduled | running | cancelled
 * scheduled --> running | draft (unschedule) | cancelled | suspended
 * running --> paused | completed | cancelled | suspended
 * paused --> running | cancelled | suspended
 * suspended --> running (resume) | cancelled
 * ```
 *
 * `suspended` = paused by the system (quota exhausted / SMTP unavailable /
 * approval revoked), see `campaigns.suspended_reason`. Archive is not a
 * status (`campaigns.archived_at`).
 *
 * Transitions are enforced exclusively through {@see CampaignService}.
 */
enum CampaignStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Running = 'running';
    case Paused = 'paused';
    case Suspended = 'suspended';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /**
     * @return list<self>
     */
    public function allowedNextStatuses(): array
    {
        return match ($this) {
            self::Draft => [self::Scheduled, self::Running, self::Cancelled],
            self::Scheduled => [self::Running, self::Draft, self::Cancelled, self::Suspended],
            self::Running => [self::Paused, self::Completed, self::Cancelled, self::Suspended],
            self::Paused => [self::Running, self::Cancelled, self::Suspended],
            self::Suspended => [self::Running, self::Cancelled],
            self::Completed, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedNextStatuses(), true);
    }

    /**
     * docs/45 §45.4.2 — campaigns can only be archived from these statuses.
     */
    public function isArchivable(): bool
    {
        return in_array($this, [self::Completed, self::Suspended, self::Cancelled], true);
    }

    /**
     * docs/45 §45.4.2 — PATCH / design are allowed only in these statuses.
     */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Scheduled, self::Suspended], true);
    }

    public function brevoStatus(bool $archived): CampaignBrevoStatus
    {
        if ($archived) {
            return CampaignBrevoStatus::Archive;
        }

        return match ($this) {
            self::Draft => CampaignBrevoStatus::Draft,
            self::Scheduled => CampaignBrevoStatus::Queued,
            self::Running => CampaignBrevoStatus::InProcess,
            self::Paused, self::Suspended => CampaignBrevoStatus::Suspended,
            self::Completed => CampaignBrevoStatus::Sent,
            self::Cancelled => CampaignBrevoStatus::Cancelled,
        };
    }

    /**
     * docs/45 §45.4.2 `status_tab` of a non-archived campaign (`null` = only in "all").
     */
    public function statusTab(): ?string
    {
        return match ($this) {
            self::Draft => 'draft',
            self::Scheduled => 'scheduled',
            self::Running, self::Paused => 'running',
            self::Suspended => 'suspended',
            self::Completed => 'sent',
            self::Cancelled => null,
        };
    }

    /**
     * docs/45 §45.4.2 `available_actions` table (state machine only;
     * `duplicate` and `move` are always available).
     *
     * @return list<CampaignAction>
     */
    public function availableActions(bool $archived, bool $hasSentMessages = false): array
    {
        $actions = $archived
            ? [CampaignAction::Report, CampaignAction::Unarchive, CampaignAction::Delete]
            : match ($this) {
                self::Draft => [CampaignAction::Edit, CampaignAction::TestSend, CampaignAction::Schedule, CampaignAction::SendNow, CampaignAction::Delete],
                self::Scheduled => [CampaignAction::Edit, CampaignAction::TestSend, CampaignAction::Unschedule, CampaignAction::SendNow, CampaignAction::Cancel],
                self::Running => [CampaignAction::Report, CampaignAction::Pause, CampaignAction::Cancel],
                self::Paused => [CampaignAction::Report, CampaignAction::Resume, CampaignAction::Cancel],
                self::Suspended => [CampaignAction::Report, CampaignAction::Edit, CampaignAction::Resume, CampaignAction::Cancel, CampaignAction::Archive],
                self::Completed => [CampaignAction::Report, CampaignAction::SendToNewContacts, CampaignAction::Archive],
                self::Cancelled => [...($hasSentMessages ? [CampaignAction::Report] : []), CampaignAction::Archive, CampaignAction::Delete],
            };

        return [...$actions, CampaignAction::Duplicate, CampaignAction::Move];
    }
}
