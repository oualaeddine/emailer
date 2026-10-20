<?php

namespace App\Modules\Transactional\Services;

use App\Modules\Tracking\Models\Message;
use App\Modules\Tracking\Models\MessageEvent;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * docs/45 §45.7.6 — transactional logs: every non-campaign, non-test message
 * (`campaign_id` null, `is_test` false). Source is read from the `queued`
 * event written by {@see TransactionalSendService}; messages without it are
 * `composer` (draft) or `automation` (automation_step_id).
 */
class TransactionalLogService
{
    /** BrevoEventLabel => message_events.event_type values. */
    public const EVENT_TYPES = [
        'sent' => [MessageEvent::SENT],
        'delivered' => [MessageEvent::DELIVERED],
        'opened' => [MessageEvent::OPENED],
        'clicked' => [MessageEvent::CLICKED],
        'soft_bounce' => [MessageEvent::SOFT_BOUNCED],
        'hard_bounce' => [MessageEvent::HARD_BOUNCED],
        'invalid' => ['invalid'],
        'deferred' => [MessageEvent::DEFERRED],
        'complaint' => [MessageEvent::COMPLAINT],
        'unsubscribed' => [MessageEvent::UNSUBSCRIBED],
        'blocked' => [MessageEvent::BLOCKED],
        'error' => [MessageEvent::FAILED],
    ];

    /**
     * @param  array<string, mixed>  $f
     * @return CursorPaginator<int, Message>
     */
    public function paginate(array $f, int $perPage = 50): CursorPaginator
    {
        $query = $this->base()->with(['recipient', 'events' => fn ($q) => $q->orderBy('occurred_at')->orderBy('id')]);

        if (filled($f['event'] ?? null)) {
            $types = self::EVENT_TYPES[$f['event']] ?? [];
            $query->whereHas('events', fn (Builder $e) => $e->whereIn('event_type', $types));
        }
        if (filled($f['q'] ?? null)) {
            $q = trim((string) $f['q']);
            $uuid = Str::of($q)->trim('<>')->before('@')->toString();
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], mb_strtolower($q)).'%';
            $query->where(function (Builder $w) use ($like, $uuid, $q): void {
                $w->whereRaw('LOWER(messages.subject) LIKE ?', [$like])
                    ->orWhere('messages.uuid', $uuid)
                    ->orWhere('messages.provider_message_id', $q)
                    ->orWhereHas('recipient', fn (Builder $r) => $r->whereRaw('LOWER(email) LIKE ?', [$like]));
            });
        }
        if (filled($f['tag'] ?? null)) {
            $tag = (string) $f['tag'];
            $query->whereHas('events', fn (Builder $e) => $e->where('event_type', TransactionalSendService::QUEUED_EVENT)
                ->whereJsonContains('metadata->tags', $tag));
        }
        if (filled($f['template_id'] ?? null)) {
            $template = (string) $f['template_id'];
            $query->whereHas('events', fn (Builder $e) => $e->where('event_type', TransactionalSendService::QUEUED_EVENT)
                ->where('metadata->template_id', $template));
        }
        if (filled($f['source'] ?? null)) {
            $this->filterSource($query, (string) $f['source']);
        }
        if (filled($f['date_from'] ?? null)) {
            $query->where('messages.created_at', '>=', $f['date_from']);
        }
        if (filled($f['date_to'] ?? null)) {
            $query->where('messages.created_at', '<=', $f['date_to']);
        }

        return $query->orderByDesc('messages.id')->cursorPaginate($perPage);
    }

    public function findByUuid(string $uuid): Message
    {
        abort_unless(Str::isUuid($uuid), 404);

        return $this->base()->with(['recipient', 'events' => fn ($q) => $q->orderBy('occurred_at')->orderBy('id')])
            ->where('messages.uuid', $uuid)
            ->firstOrFail();
    }

    /** @return Builder<Message> */
    private function base(): Builder
    {
        return Message::query()->whereNull('messages.campaign_id')->where('messages.is_test', false);
    }

    /**
     * @param  Builder<Message>  $query
     */
    private function filterSource(Builder $query, string $source): void
    {
        $hasStep = Schema::hasColumn('messages', 'automation_step_id');
        $queuedBy = fn (string $s) => fn (Builder $e) => $e->where('event_type', TransactionalSendService::QUEUED_EVENT)->where('metadata->source', $s);

        match ($source) {
            'composer' => $query->whereNotNull('messages.draft_id'),
            'automation' => $query->where(fn (Builder $w) => $w->whereHas('events', $queuedBy('automation'))
                ->when($hasStep, fn (Builder $x) => $x->orWhereNotNull('messages.automation_step_id'))),
            default => $query->whereHas('events', $queuedBy($source)),
        };
    }
}
