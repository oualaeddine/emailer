<?php

namespace App\Modules\Tracking\Services;

use App\Domain\Enums\MessageStatus;
use App\Domain\Enums\PermissionName;
use App\Modules\Identity\Models\User;
use App\Modules\Tracking\Models\Message;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * docs/16-email-history.md, docs/20-delivery-tracking.md — Email
 * History / Delivery Tracking read model.
 *
 * Own-vs-all visibility (docs/16-email-history.md, docs/26-rbac.md §26.4,
 * mirroring App\Modules\Composer\Policies\DraftPolicy's own/all pattern):
 * a `messages` row has no direct `user_id` column (docs/04-database-design.md
 * §4.10) — the "sending user" is traced through `messages.draft_id` ->
 * `drafts.user_id` (transactional sends always go through a Draft, see
 * App\Modules\DeliveryEngine\Services\ComposerSendService). Campaign-originated
 * messages (`draft_id` null, `campaign_id` set) have no owning user yet since
 * the Campaigns module does not exist in this build wave
 * (docs/34-roadmap.md build order) — a `mailbox.view_own`-only user will not
 * see those rows until Campaigns introduces its own ownership concept;
 * `mailbox.view_all` users see everything regardless.
 */
class MessageService
{
    /**
     * docs/16-email-history.md §16.1 "Sent Items" mailbox-folder-equivalent
     * grouping, mapped onto the real docs/20-delivery-tracking.md §20.1
     * status enum. Only groups with real status-enum backing are
     * implemented: `outbox` (not yet delivered), `sent` (successfully
     * progressed past sending), `failed` (terminal negative outcomes).
     *
     * NOTE: a `scheduled` folder is explicitly NOT implemented here. The
     * `messages.status` enum (doc 20 §20.1) has no distinct "scheduled"
     * value and `messages` has no future-dated `scheduled_at` column (only
     * `queued_at`, stamped when the row is created and a job is already
     * dispatched per §20.1's `queued` trigger) — there is no schema-backed
     * way to distinguish "queued now" from "scheduled for later" yet. That
     * is expected to arrive with campaign scheduling (`campaign_schedules`,
     * docs/16-email-history.md §16.4), out of scope for this module. An
     * unrecognized/unimplemented `folder` filter value is simply ignored
     * (no-op) rather than erroring, so the API stays forward-compatible.
     *
     * @var array<string, list<MessageStatus>>
     */
    private const FOLDER_STATUS_MAP = [
        'outbox' => [MessageStatus::Queued, MessageStatus::Sending],
        'sent' => [MessageStatus::Accepted, MessageStatus::Delivered, MessageStatus::Opened, MessageStatus::Clicked],
        'failed' => [
            MessageStatus::SoftBounced,
            MessageStatus::HardBounced,
            MessageStatus::Failed,
            MessageStatus::Rejected,
            MessageStatus::SpamComplaint,
            MessageStatus::Unsubscribed,
        ],
    ];

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForUser(User $user, array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = Message::query()->with(['recipient', 'smtpAccount', 'draft']);

        $this->scopeToUser($query, $user);

        return $this->applyFilters($query, $filters)
            ->orderByDesc('queued_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findByUuid(string $uuid): Message
    {
        return Message::query()
            ->with(['recipient', 'smtpAccount', 'draft', 'sendAttempts'])
            ->where('uuid', $uuid)
            ->firstOrFail();
    }

    public function canViewAny(User $user): bool
    {
        return $user->hasAnyPermission(PermissionName::MailboxViewOwn->value, PermissionName::MailboxViewAll->value);
    }

    public function canView(User $user, Message $message): bool
    {
        if ($user->hasPermission(PermissionName::MailboxViewAll->value)) {
            return true;
        }

        return $user->hasPermission(PermissionName::MailboxViewOwn->value)
            && $message->draft !== null
            && $message->draft->user_id === $user->id;
    }

    private function scopeToUser(Builder $query, User $user): void
    {
        if ($user->hasPermission(PermissionName::MailboxViewAll->value)) {
            return;
        }

        if ($user->hasPermission(PermissionName::MailboxViewOwn->value)) {
            $query->whereHas('draft', fn (Builder $d) => $d->where('user_id', $user->id));

            return;
        }

        // No mailbox permission at all — the controller is expected to have
        // already aborted before reaching here; this is a defensive fallback.
        $query->whereRaw('1 = 0');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        if (! empty($filters['folder']) && isset(self::FOLDER_STATUS_MAP[$filters['folder']])) {
            $query->whereIn('status', array_map(
                fn (MessageStatus $s) => $s->value,
                self::FOLDER_STATUS_MAP[$filters['folder']],
            ));
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['smtp_account_id'])) {
            $query->where('smtp_account_id', $filters['smtp_account_id']);
        }

        if (! empty($filters['campaign_id'])) {
            $query->where('campaign_id', $filters['campaign_id']);
        }

        if (! empty($filters['q'])) {
            $q = $filters['q'];
            $query->whereHas('recipient', function (Builder $r) use ($q): void {
                $r->where('email', 'like', "%{$q}%")
                    ->orWhere('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%")
                    ->orWhere('company_name', 'like', "%{$q}%");
            });
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('queued_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('queued_at', '<=', $filters['date_to']);
        }

        return $query;
    }
}
