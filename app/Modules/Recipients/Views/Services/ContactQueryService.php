<?php

namespace App\Modules\Recipients\Views\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Recipients\Models\RecipientList;
use App\Modules\Recipients\Models\Segment;
use App\Modules\Recipients\Views\Contracts\ContactSelection;
use App\Modules\Recipients\Views\Contracts\RuleTreeCompilerName;
use App\Modules\Recipients\Views\Models\ContactView;
use App\Modules\Recipients\Views\Support\ContactColumns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * docs/45 §45.5.4 — contacts index query: filters (rule tree through the
 * WP-54 compiler contract), list / segment / view scoping, `q`, whitelisted
 * columns and sort, opaque cursor pagination.
 */
class ContactQueryService
{
    /**
     * Merges a saved view's defaults under the explicit query parameters.
     *
     * @param  array<string, mixed>  $params
     * @return array{q: ?string, scope: string, list_id: ?string, segment_id: ?string, filters: ?array<string, mixed>, columns: list<string>, sort: string, source: ?string, status: ?string, tag_id: mixed}
     */
    public function effective(array $params, ?User $user): array
    {
        $view = $this->resolveView($params['view_id'] ?? null, $user);
        $scope = $params['scope'] ?? 'all';

        if (($params['view_id'] ?? null) === 'mine') {
            $scope = 'mine';
        }

        $columns = $params['columns'] ?? null;
        if ($columns === null || $columns === []) {
            $columns = $view?->columns ?: ContactColumns::defaults();
        }

        return [
            'q' => $params['q'] ?? null,
            'scope' => $scope,
            'list_id' => $params['list_id'] ?? ($view?->list?->uuid),
            'segment_id' => $params['segment_id'] ?? ($view?->segment?->uuid),
            'filters' => $params['filters'] ?? $view?->filters,
            'columns' => ContactColumns::keys($columns),
            'sort' => $params['sort'] ?? $view?->sort ?? ContactColumns::DEFAULT_SORT,
            'source' => $params['source'] ?? null,
            'status' => $params['status'] ?? null,
            'tag_id' => $params['tag_id'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $effective  Output of {@see self::effective()}
     * @return Builder<Recipient>
     */
    public function query(array $effective, ?User $user): Builder
    {
        $query = Recipient::query();

        if (($effective['scope'] ?? 'all') === 'mine') {
            if (ContactColumns::hasRecipientColumn('created_by')) {
                $query->where('recipients.created_by', $user?->id ?? 0);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        if (! empty($effective['q'])) {
            // `%` / `_` are literal; case-insensitive on every driver (LOWER … LIKE).
            $term = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower((string) $effective['q'])).'%';
            $columns = ['email', 'first_name', 'last_name', 'company_name'];
            if (ContactColumns::hasRecipientColumn('ext_id')) {
                $columns[] = 'ext_id';
            }
            $grammar = $query->getQuery()->getGrammar();
            $query->where(function (Builder $inner) use ($columns, $term, $grammar): void {
                foreach ($columns as $column) {
                    $inner->orWhereRaw('LOWER('.$grammar->wrap('recipients.'.$column).') LIKE ?', [$term]);
                }
            });
        }

        foreach (['source', 'status'] as $legacy) {
            if (! empty($effective[$legacy])) {
                $query->where("recipients.{$legacy}", $effective[$legacy]);
            }
        }

        if (! empty($effective['tag_id'])) {
            $query->whereHas('tags', fn (Builder $t) => $t->where('tags.id', $effective['tag_id']));
        }

        if (! empty($effective['list_id'])) {
            $list = RecipientList::query()->where('uuid', $effective['list_id'])->first();
            if ($list === null) {
                throw ValidationException::withMessages(['list_id' => ['Liste introuvable.']]);
            }
            $query->whereIn(
                'recipients.id',
                DB::table('recipient_list_recipient')->select('recipient_id')->where('recipient_list_id', $list->id),
            );
        }

        if (! empty($effective['segment_id'])) {
            $segment = Segment::query()->where('uuid', $effective['segment_id'])->first();
            if ($segment === null) {
                throw ValidationException::withMessages(['segment_id' => ['Segment introuvable.']]);
            }
            $this->applyRules($query, (array) $segment->rules, 'segment_id');
        }

        if (! empty($effective['filters'])) {
            $this->applyRules($query, $effective['filters'], 'filters');
        }

        return $query;
    }

    /**
     * Validates a rule tree through the WP-54 compiler (used when saving views).
     *
     * @param  array<string, mixed>  $tree
     */
    public function validateRules(array $tree, string $field = 'filters'): void
    {
        if ($this->isEmptyTree($tree)) {
            return;
        }

        $this->compiler($field)->validate($tree);
    }

    /**
     * @param  Builder<Recipient>  $query
     * @param  array<string, mixed>  $tree
     */
    private function applyRules(Builder $query, array $tree, string $field): void
    {
        if ($this->isEmptyTree($tree)) {
            return;
        }

        // apply() validates the tree's shape; unknown list / campaign references
        // in a saved segment or view match nobody instead of failing.
        $compiler = $this->compiler($field);
        $query->where(function (Builder $group) use ($compiler, $tree): void {
            $compiler->apply($group, $tree);
        });
    }

    /** @param  array<string, mixed>  $tree */
    private function isEmptyTree(array $tree): bool
    {
        return $tree === [] || (array_key_exists('children', $tree) && $tree['children'] === []);
    }

    private function compiler(string $field): object
    {
        if (! app()->bound(RuleTreeCompilerName::CONTRACT)) {
            throw ValidationException::withMessages([$field => ['Filtres indisponibles pour le moment.']]);
        }

        return app(RuleTreeCompilerName::CONTRACT);
    }

    private function resolveView(?string $viewId, ?User $user): ?ContactView
    {
        if ($viewId === null || $viewId === '' || in_array($viewId, ['all', 'mine'], true)) {
            return null;
        }

        $view = ContactView::query()
            ->where('uuid', $viewId)
            ->where(fn (Builder $q) => $q->where('user_id', $user?->id ?? 0)->orWhere('is_shared', true))
            ->first();

        if ($view === null) {
            throw ValidationException::withMessages(['view_id' => ['Vue introuvable.']]);
        }

        return $view;
    }

    /**
     * Cursor-paginated rows (opaque keyset-free cursor, stable `id` tie-breaker).
     *
     * @param  array<string, mixed>  $effective
     * @return array<string, mixed>  ContactIndexResponse
     */
    public function paginate(array $effective, ?User $user, int $perPage, ?string $cursor, string $path): array
    {
        $query = $this->query($effective, $user);
        [$expression, $direction] = ContactColumns::sortExpression($effective['sort']);
        $offset = $this->decodeCursor($cursor);

        $total = (clone $query)->count();

        $with = ['tags'];
        if (in_array('lists', $effective['columns'], true)) {
            $with[] = 'recipientLists:recipient_lists.id,recipient_lists.uuid,recipient_lists.name';
        }

        $rows = $query->with($with)
            ->orderBy($expression, $direction)
            ->orderBy('recipients.id', $direction)
            ->offset($offset)
            ->limit($perPage + 1)
            ->get();

        $hasMore = $rows->count() > $perPage;
        $page = $rows->take($perPage);
        $next = $hasMore ? $this->encodeCursor($offset + $perPage) : null;
        $prev = $offset > 0 ? $this->encodeCursor(max(0, $offset - $perPage)) : null;

        return [
            'data' => $this->toRows($page->all(), $effective['columns']),
            'links' => [
                'first' => null,
                'last' => null,
                'prev' => $prev !== null ? $path.'?cursor='.urlencode($prev) : null,
                'next' => $next !== null ? $path.'?cursor='.urlencode($next) : null,
            ],
            'meta' => [
                'path' => $path,
                'per_page' => $perPage,
                'next_cursor' => $next,
                'prev_cursor' => $prev,
                'total' => $total,
                'columns' => $effective['columns'],
            ],
        ];
    }

    /**
     * @param  list<Recipient>  $recipients
     * @param  list<string>  $columns
     * @return list<array<string, mixed>>
     */
    public function toRows(array $recipients, array $columns): array
    {
        $creatorIds = array_values(array_filter(array_unique(array_map(
            fn (Recipient $r) => $r->getAttribute('created_by'),
            $recipients,
        ))));
        $creators = $creatorIds === []
            ? collect()
            : User::query()->whereIn('id', $creatorIds)->get(['id', 'uuid', 'name'])->keyBy('id');

        $withLists = in_array('lists', $columns, true);

        return array_map(function (Recipient $r) use ($columns, $creators, $withLists): array {
            $blocklistedAt = ContactColumns::iso($r->getAttribute('blocklisted_at'));
            $creator = $creators->get($r->getAttribute('created_by'));

            $attributes = [];
            foreach ($columns as $key) {
                $parsed = ContactColumns::parse($key);
                if ($parsed !== null && $parsed['kind'] === 'attribute') {
                    $attributes[$parsed['name']] = ContactColumns::value($r, $key);
                }
            }

            $row = [
                'id' => $r->uuid,
                'email' => $r->email,
                'first_name' => $r->first_name,
                'last_name' => $r->last_name,
                'company_name' => $r->company_name,
                'ext_id' => $r->getAttribute('ext_id'),
                'source' => $r->source,
                'status' => $r->status,
                'blocklisted' => $blocklistedAt !== null || $r->status === 'suppressed',
                'blocklisted_at' => $blocklistedAt,
                'opt_in_at' => ContactColumns::iso($r->getAttribute('opt_in_at')),
                'opt_in_source' => $r->getAttribute('opt_in_source'),
                'last_contacted_at' => ContactColumns::iso($r->last_contacted_at),
                'last_opened_at' => ContactColumns::iso($r->getAttribute('last_opened_at')),
                'last_clicked_at' => ContactColumns::iso($r->getAttribute('last_clicked_at')),
                'engagement_score' => $r->getAttribute('engagement_score'),
                'attributes' => (object) $attributes,
                'tags' => $r->relationLoaded('tags')
                    ? $r->tags->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'color' => $t->color])->all()
                    : [],
                'created_by' => $creator !== null ? ['id' => $creator->uuid, 'name' => $creator->name] : null,
                'created_at' => ContactColumns::iso($r->created_at),
                'updated_at' => ContactColumns::iso($r->updated_at),
            ];

            if ($withLists) {
                $row['list_ids'] = $r->recipientLists->pluck('uuid')->all();
            }

            return $row;
        }, $recipients);
    }

    /**
     * Resolves a ContactSelection (doc 45 §45.3) into a recipients query.
     *
     * @param  array<string, mixed>  $selection
     * @return Builder<Recipient>
     */
    public function selectionQuery(array $selection, ?User $user): Builder
    {
        $hasIds = ! empty($selection['ids']);
        $hasAll = array_key_exists('all_matching', $selection) && $selection['all_matching'] !== null;

        if ($hasIds === $hasAll) {
            throw ValidationException::withMessages([
                'selection' => ['Fournissez soit « ids », soit « all_matching ».'],
            ]);
        }

        if ($hasIds) {
            return Recipient::query()->whereIn('recipients.uuid', $selection['ids']);
        }

        $query = $this->query($this->effective((array) $selection['all_matching'], $user), $user);

        if (! empty($selection['exclude_ids'])) {
            $query->whereNotIn('recipients.uuid', $selection['exclude_ids']);
        }

        if ((clone $query)->count() > ContactSelection::MAX_ALL_MATCHING) {
            throw ValidationException::withMessages([
                'selection' => ['Sélection trop large (maximum '.number_format(ContactSelection::MAX_ALL_MATCHING, 0, ',', ' ').' contacts).'],
            ]);
        }

        return $query;
    }

    private function encodeCursor(int $offset): string
    {
        return rtrim(strtr(base64_encode(json_encode(['o' => $offset])), '+/', '-_'), '=');
    }

    private function decodeCursor(?string $cursor): int
    {
        if ($cursor === null || $cursor === '') {
            return 0;
        }

        $decoded = json_decode((string) base64_decode(strtr($cursor, '-_', '+/'), true), true);

        if (! is_array($decoded) || ! isset($decoded['o']) || ! is_int($decoded['o']) || $decoded['o'] < 0) {
            throw ValidationException::withMessages(['cursor' => ['Curseur invalide.']]);
        }

        return $decoded['o'];
    }
}
