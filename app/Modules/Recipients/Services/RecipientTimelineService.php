<?php

namespace App\Modules\Recipients\Services;

use App\Modules\Recipients\Models\Recipient;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * docs/45-brevo-api-contracts.md §45.5.5 — merged, newest-first activity
 * timeline of one contact.
 *
 * Sources: contact creation, `messages` (sent / delivered / opened / clicked /
 * bounce / complaint / unsubscribed / blocked), `message_events` when that
 * table exists (it then supersedes the engagement timestamps of `messages`),
 * imports (through `import_rows.created_recipient_id`) and list membership
 * (`recipient_list_recipient.added_at`). Each source is read through a
 * keyset query, so a page costs O(per_page) rows per source whatever the
 * contact's history size.
 */
class RecipientTimelineService
{
    public const DEFAULT_PER_PAGE = 25;

    public const MAX_PER_PAGE = 100;

    /** @var list<string> */
    public const TYPES = [
        'created', 'sent', 'delivered', 'opened', 'clicked', 'soft_bounce', 'hard_bounce', 'deferred',
        'blocked', 'unsubscribed', 'complaint', 'imported', 'list_added', 'list_removed',
        'attribute_updated', 'form_submitted', 'opt_in_confirmed', 'blocklisted', 'unblocklisted',
        'automation_entered', 'note_added',
    ];

    /** message_events.event_type => timeline type. */
    private const EVENT_TYPES = [
        'opened' => 'opened',
        'clicked' => 'clicked',
        'soft_bounced' => 'soft_bounce',
        'hard_bounced' => 'hard_bounce',
        'spam_complaint' => 'complaint',
        'unsubscribed' => 'unsubscribed',
    ];

    /**
     * @param  list<string>  $types  empty = every type
     * @return array{data: list<array<string, mixed>>, next_cursor: string|null}
     */
    public function page(Recipient $recipient, ?string $cursor, int $perPage, array $types = []): array
    {
        $perPage = max(1, min(self::MAX_PER_PAGE, $perPage));
        $after = $this->decodeCursor($cursor);
        $wanted = $types === [] ? null : array_flip($types);

        $candidates = collect();
        foreach ($this->sources($recipient) as $source) {
            if ($wanted !== null && ! isset($wanted[$source['type']])) {
                continue;
            }
            $candidates = $candidates->concat($this->fetch($source, $after, $perPage + 1));
        }

        $sorted = $candidates
            ->filter(fn (array $e): bool => $after === null || $this->isBefore($e, $after))
            ->sort(fn (array $a, array $b): int => [$b['_ts'], $b['id']] <=> [$a['_ts'], $a['id']])
            ->values();

        $slice = $sorted->take($perPage);
        $next = $sorted->count() > $perPage ? $this->encodeCursor($slice->last()) : null;

        return [
            'data' => $this->hydrate($slice)->all(),
            'next_cursor' => $next,
        ];
    }

    /**
     * @return array{messages_sent: int, delivered: int, opened: int, clicked: int, hard_bounces: int, soft_bounces: int, complaints: int, unsubscriptions: int}
     */
    public function counters(Recipient $recipient): array
    {
        $out = [];
        foreach (['messages_sent', 'delivered', 'opened', 'clicked', 'hard_bounces', 'soft_bounces', 'complaints', 'unsubscriptions'] as $key) {
            $out[$key] = (int) $recipient->getAttribute($key);
        }

        return $out;
    }

    /**
     * Each source: type, builder (already scoped to the contact), timestamp
     * column, mapper (row => entry fields|null).
     *
     * @return list<array{type: string, query: Builder, column: string, map: Closure}>
     */
    private function sources(Recipient $recipient): array
    {
        $id = $recipient->id;
        $messages = fn (): Builder => DB::table('messages')
            ->where('recipient_id', $id)->where('is_test', false)
            ->select(['id', 'uuid', 'campaign_id', 'subject', 'status', 'sent_at', 'delivered_at', 'opened_at', 'clicked_at', 'bounced_at', 'failed_at', 'updated_at']);

        $msg = fn (string $type, string $column): Closure => fn (object $r): array => [
            'type' => $type,
            'occurred_at' => $r->{$column},
            'key' => "msg:{$r->uuid}:{$type}",
            'campaign_id' => $r->campaign_id,
            'message_id' => $r->uuid,
            'subject' => $r->subject,
        ];

        $sources = [
            [
                'type' => 'created',
                'query' => DB::table('recipients')->where('id', $id)->select(['id', 'uuid', 'created_at']),
                'column' => 'created_at',
                'map' => fn (object $r): array => ['type' => 'created', 'occurred_at' => $r->created_at, 'key' => "created:{$r->uuid}"],
            ],
            ['type' => 'sent', 'query' => $messages()->whereNotNull('sent_at'), 'column' => 'sent_at', 'map' => $msg('sent', 'sent_at')],
            ['type' => 'delivered', 'query' => $messages()->whereNotNull('delivered_at'), 'column' => 'delivered_at', 'map' => $msg('delivered', 'delivered_at')],
            ['type' => 'blocked', 'query' => $messages()->whereNotNull('failed_at'), 'column' => 'failed_at', 'map' => $msg('blocked', 'failed_at')],
        ];

        if ($this->hasEventsTable()) {
            foreach (self::EVENT_TYPES as $eventType => $type) {
                $sources[] = [
                    'type' => $type,
                    'query' => DB::table('message_events as e')
                        ->join('messages as m', 'm.id', '=', 'e.message_id')
                        ->where('m.recipient_id', $id)->where('m.is_test', false)->where('e.event_type', $eventType)
                        ->select(['e.id as event_id', 'e.occurred_at', 'e.metadata', 'm.uuid', 'm.campaign_id', 'm.subject']),
                    'column' => 'e.occurred_at',
                    'map' => function (object $r) use ($type): array {
                        $meta = is_string($r->metadata) ? (json_decode($r->metadata, true) ?: []) : [];

                        return [
                            'type' => $type,
                            'occurred_at' => $r->occurred_at,
                            'key' => "evt:{$r->event_id}",
                            'campaign_id' => $r->campaign_id,
                            'message_id' => $r->uuid,
                            'subject' => $r->subject,
                            'url' => $meta['url'] ?? null,
                            'device_type' => $meta['device_type'] ?? null,
                            'email_client' => $meta['email_client'] ?? null,
                            'ip_country' => $meta['ip_country'] ?? null,
                        ];
                    },
                ];
            }
        } else {
            $sources[] = ['type' => 'opened', 'query' => $messages()->whereNotNull('opened_at'), 'column' => 'opened_at', 'map' => $msg('opened', 'opened_at')];
            $sources[] = ['type' => 'clicked', 'query' => $messages()->whereNotNull('clicked_at'), 'column' => 'clicked_at', 'map' => $msg('clicked', 'clicked_at')];
            $sources[] = ['type' => 'soft_bounce', 'query' => $messages()->where('status', 'soft_bounced')->whereNotNull('bounced_at'), 'column' => 'bounced_at', 'map' => $msg('soft_bounce', 'bounced_at')];
            $sources[] = ['type' => 'hard_bounce', 'query' => $messages()->where('status', 'hard_bounced')->whereNotNull('bounced_at'), 'column' => 'bounced_at', 'map' => $msg('hard_bounce', 'bounced_at')];
            $sources[] = ['type' => 'complaint', 'query' => $messages()->where('status', 'spam_complaint'), 'column' => 'updated_at', 'map' => $msg('complaint', 'updated_at')];
            $sources[] = ['type' => 'unsubscribed', 'query' => $messages()->where('status', 'unsubscribed'), 'column' => 'updated_at', 'map' => $msg('unsubscribed', 'updated_at')];
        }

        $sources[] = [
            'type' => 'imported',
            'query' => DB::table('import_rows as ir')
                ->join('import_jobs as j', 'j.id', '=', 'ir.import_job_id')
                ->leftJoin('users as u', 'u.id', '=', 'j.user_id')
                ->where('ir.created_recipient_id', $id)
                ->selectRaw('ir.id as row_id, j.uuid as job_uuid, COALESCE(j.completed_at, j.started_at, j.created_at) as at, u.uuid as user_uuid, u.name as user_name'),
            'column' => DB::raw('COALESCE(j.completed_at, j.started_at, j.created_at)'),
            'map' => fn (object $r): array => [
                'type' => 'imported',
                'occurred_at' => $r->at,
                'key' => "import:{$r->job_uuid}",
                'import_job_id' => $r->job_uuid,
                'actor' => $r->user_uuid ? ['id' => $r->user_uuid, 'name' => $r->user_name] : null,
            ],
        ];

        $sources[] = [
            'type' => 'list_added',
            'query' => DB::table('recipient_list_recipient as p')
                ->join('recipient_lists as l', 'l.id', '=', 'p.recipient_list_id')
                ->where('p.recipient_id', $id)->whereNotNull('p.added_at')
                ->select(['l.uuid as list_uuid', 'l.name as list_name', 'p.added_at']),
            'column' => 'p.added_at',
            'map' => fn (object $r): array => [
                'type' => 'list_added',
                'occurred_at' => $r->added_at,
                'key' => "list:{$r->list_uuid}:added",
                'list' => ['id' => $r->list_uuid, 'name' => $r->list_name],
            ],
        ];

        return $sources;
    }

    /**
     * @param  array{type: string, query: Builder, column: mixed, map: Closure}  $source
     * @param  array{ts: int, id: string}|null  $after
     * @return list<array<string, mixed>>
     */
    private function fetch(array $source, ?array $after, int $limit): array
    {
        $query = $source['query'];
        $column = $source['column'];

        if ($after !== null) {
            $bound = CarbonImmutable::createFromTimestampUTC($after['ts'])->toDateTimeString();
            // Rows strictly older than the cursor second, plus every row of that second
            // (ties are then resolved on the entry id in PHP).
            $query->where($column, '<=', $bound);
        }

        $rows = $query->orderByDesc($column)->limit($limit + 50)->get();

        $entries = [];
        foreach ($rows as $row) {
            $fields = ($source['map'])($row);
            if ($fields === null || $fields['occurred_at'] === null) {
                continue;
            }
            $at = CarbonImmutable::parse($fields['occurred_at'], 'UTC');
            $entries[] = ['id' => $fields['key'], '_ts' => $at->getTimestamp(), '_at' => $at, '_fields' => $fields];
        }

        return $entries;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $entries
     * @return Collection<int, array<string, mixed>>
     */
    private function hydrate(Collection $entries): Collection
    {
        $campaignIds = $entries->pluck('_fields.campaign_id')->filter()->unique()->all();
        $campaigns = $campaignIds === []
            ? collect()
            : DB::table('campaigns')->whereIn('id', $campaignIds)->get(['id', 'uuid', 'name'])->keyBy('id');

        return $entries->map(function (array $e) use ($campaigns): array {
            $f = $e['_fields'];
            $campaign = isset($f['campaign_id']) ? $campaigns->get($f['campaign_id']) : null;

            return [
                'id' => $e['id'],
                'type' => $f['type'],
                'occurred_at' => $e['_at']->toIso8601ZuluString(),
                'campaign' => $campaign ? ['id' => $campaign->uuid, 'name' => $campaign->name] : null,
                'message_id' => $f['message_id'] ?? null,
                'subject' => $f['subject'] ?? null,
                'list' => $f['list'] ?? null,
                'import_job_id' => $f['import_job_id'] ?? null,
                'form_id' => null,
                'automation_id' => null,
                'url' => $f['url'] ?? null,
                'device_type' => $f['device_type'] ?? null,
                'email_client' => $f['email_client'] ?? null,
                'ip_country' => $f['ip_country'] ?? null,
                'reason' => null,
                'actor' => $f['actor'] ?? null,
            ];
        });
    }

    private function hasEventsTable(): bool
    {
        return Schema::hasTable('message_events');
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  array{ts: int, id: string}  $after
     */
    private function isBefore(array $entry, array $after): bool
    {
        return [$entry['_ts'], $entry['id']] < [$after['ts'], $after['id']];
    }

    /**
     * @param  array<string, mixed>|null  $entry
     */
    private function encodeCursor(?array $entry): ?string
    {
        if ($entry === null) {
            return null;
        }

        return rtrim(strtr(base64_encode(json_encode([$entry['_ts'], $entry['id']], JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
    }

    /**
     * @return array{ts: int, id: string}|null
     */
    private function decodeCursor(?string $cursor): ?array
    {
        if ($cursor === null || $cursor === '') {
            return null;
        }

        $decoded = json_decode((string) base64_decode(strtr($cursor, '-_', '+/')), true);
        if (! is_array($decoded) || count($decoded) !== 2 || ! is_int($decoded[0]) || ! is_string($decoded[1])) {
            abort(422, 'Curseur invalide.');
        }

        return ['ts' => $decoded[0], 'id' => $decoded[1]];
    }
}
