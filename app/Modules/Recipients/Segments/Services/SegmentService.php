<?php

namespace App\Modules\Recipients\Segments\Services;

use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Identity\Models\User;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Recipients\Models\RecipientList;
use App\Modules\Recipients\Models\Segment;
use App\Modules\Recipients\Segments\Contracts\SegmentCompilerContract;
use App\Modules\Recipients\Segments\Exceptions\SegmentInUseException;
use App\Modules\Recipients\Services\SegmentEvaluationService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * docs/45-brevo-api-contracts.md §45.5.2 — standalone segments (CRUD, live
 * count, duplicate, legacy preview; members: ContactQueryService).
 */
class SegmentService
{
    public const SORTS = ['name', '-name', 'updated_at', '-updated_at', 'contact_count', '-contact_count'];

    private const ACTIVE_CAMPAIGN_STATUSES = ['scheduled', 'running'];

    public function __construct(
        private readonly SegmentCompilerContract $compiler,
        private readonly SegmentEvaluationService $evaluation,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{q?: ?string, folder_id?: ?string, sort?: ?string}  $filters
     * @return LengthAwarePaginator<int, Segment>
     */
    public function list(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = Segment::query()->with('createdBy');

        if (filled($filters['q'] ?? null)) {
            $query->whereRaw('LOWER(name) LIKE ?', ['%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($filters['q'])).'%']);
        }

        $folder = $filters['folder_id'] ?? null;
        if ($folder === 'none') {
            $query->whereNull('folder_id');
        } elseif (filled($folder)) {
            $query->where('folder_id', $this->folderId($folder, 'folder_id') ?? 0);
        }

        $sort = $filters['sort'] ?? '-updated_at';
        $column = ltrim($sort, '-');
        $query->orderBy($column, str_starts_with($sort, '-') ? 'desc' : 'asc')->orderByDesc('id');

        return $query->paginate($perPage);
    }

    public function findByUuid(string $uuid): Segment
    {
        return Segment::query()->with('createdBy')->where('uuid', $uuid)->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $input  validated SegmentInput
     */
    public function create(array $input, User $actor): Segment
    {
        $rules = $this->compiler->normalize($input['rules']);

        $segment = Segment::query()->create([
            'name' => $input['name'],
            'description' => $input['description'] ?? null,
            'folder_id' => $this->folderId($input['folder_id'] ?? null),
            'recipient_list_id' => $this->legacyListId($input['recipient_list_id'] ?? null),
            'rules' => $rules,
            'rules_version' => 2,
            'created_by' => $actor->id,
        ]);
        $this->storeCount($segment);

        $this->audit->record('segment.created', $segment, $actor, null, ['name' => $segment->name]);

        return $segment->fresh('createdBy');
    }

    /**
     * @param  array<string, mixed>  $input  validated partial SegmentInput
     */
    public function update(Segment $segment, array $input, User $actor): Segment
    {
        $old = $segment->only(['name', 'description', 'folder_id', 'rules']);
        $changes = [];

        foreach (['name', 'description'] as $key) {
            if (array_key_exists($key, $input)) {
                $changes[$key] = $input[$key];
            }
        }
        if (array_key_exists('folder_id', $input)) {
            $changes['folder_id'] = $this->folderId($input['folder_id']);
        }
        if (array_key_exists('recipient_list_id', $input)) {
            $changes['recipient_list_id'] = $this->legacyListId($input['recipient_list_id']);
        }
        $rulesChanged = array_key_exists('rules', $input);
        if ($rulesChanged) {
            $changes['rules'] = $this->compiler->normalize($input['rules']);
            $changes['rules_version'] = 2;
        }

        $segment->update($changes);
        if ($rulesChanged) {
            $this->evaluation->forget($segment);
            $this->storeCount($segment);
        }

        $this->audit->record('segment.updated', $segment, $actor, $old, array_intersect_key($changes, $old));

        return $segment->fresh('createdBy');
    }

    /**
     * @throws SegmentInUseException
     */
    public function delete(Segment $segment, User $actor): void
    {
        $active = DB::table('campaigns')
            ->whereIn('id', $this->campaignIdsQuery($segment->id))
            ->whereIn('status', self::ACTIVE_CAMPAIGN_STATUSES)
            ->exists();

        if ($active) {
            throw new SegmentInUseException('Ce segment est utilisé par une campagne programmée ou en cours d’envoi.');
        }

        DB::transaction(function () use ($segment): void {
            // campaigns.segment_id is nullOnDelete; audience rows have no FK.
            DB::table('campaign_audiences')->where('target_type', 'segment')->where('target_id', $segment->id)->delete();
            $segment->delete();
        });
        $this->evaluation->forget($segment);

        $this->audit->record('segment.deleted', $segment, $actor, ['name' => $segment->name]);
    }

    public function duplicate(Segment $segment, ?string $name, User $actor): Segment
    {
        $copy = Segment::query()->create([
            'name' => $name ?? mb_substr($segment->name.' (copie)', 0, 150),
            'description' => $segment->description,
            'folder_id' => $segment->folder_id,
            'rules' => $this->rulesOf($segment),
            'rules_version' => 2,
            'contact_count' => $segment->contact_count,
            'count_computed_at' => $segment->count_computed_at,
            'created_by' => $actor->id,
        ]);

        $this->audit->record('segment.duplicated', $copy, $actor, null, ['source_id' => $segment->uuid, 'name' => $copy->name]);

        return $copy->fresh('createdBy');
    }

    /**
     * §45.5.2 `SegmentCount` for an unsaved tree (COUNT query only).
     *
     * @param  array<string, mixed>  $rules
     * @return array{count: int, computed_at: string, duration_ms: int}
     */
    public function count(array $rules): array
    {
        $started = hrtime(true);
        $count = $this->compiler->count($rules);

        return [
            'count' => $count,
            'computed_at' => now()->toIso8601ZuluString(),
            'duration_ms' => (int) round((hrtime(true) - $started) / 1_000_000),
        ];
    }

    /**
     * Recomputes and caches `contact_count`.
     *
     * @return array{count: int, computed_at: string, duration_ms: int}
     */
    public function refreshCount(Segment $segment): array
    {
        return $this->storeCount($segment);
    }

    /**
     * Legacy `segments/preview` behaviour (doc 29 §29.4): count + 10-row
     * sample of the campaign-eligible audience (suppressed excluded).
     *
     * @param  array<string, mixed>  $rules
     * @return array{count: int, sample: Collection<int, Recipient>}
     */
    public function preview(array $rules): array
    {
        $query = $this->evaluation->compile($rules);

        return [
            'count' => (clone $query)->count(),
            'sample' => $query->with('tags')->orderBy('recipients.id')->limit(10)->get(),
        ];
    }

    /**
     * Rules as a v2 tree (legacy v1 rows are converted on read).
     *
     * @return array<string, mixed>
     */
    public function rulesOf(Segment $segment): array
    {
        try {
            return $this->compiler->normalize($segment->rules ?? []);
        } catch (\InvalidArgumentException) {
            return $segment->rules ?? [];
        }
    }

    /**
     * `used_in` counts for a page of segments (one query per source).
     *
     * @param  Collection<int, Segment>  $segments
     * @return array<int, array{campaigns: int, automations: int}>
     */
    public function usedIn(Collection $segments): array
    {
        $ids = $segments->pluck('id')->all();
        $pairs = DB::table('campaigns')->whereIn('segment_id', $ids)->get(['segment_id as sid', 'id as cid'])
            ->concat(DB::table('campaign_audiences')->where('target_type', 'segment')->whereIn('target_id', $ids)->get(['target_id as sid', 'campaign_id as cid']));

        $map = [];
        foreach ($ids as $id) {
            $map[$id] = ['campaigns' => $pairs->where('sid', $id)->pluck('cid')->unique()->count(), 'automations' => 0];
        }

        return $map;
    }

    /**
     * Folder uuids for a page of segments.
     *
     * @param  Collection<int, Segment>  $segments
     * @return array<int, string> folder id => uuid
     */
    public function folderUuids(Collection $segments): array
    {
        $ids = $segments->pluck('folder_id')->filter()->unique()->values()->all();
        if ($ids === [] || ! Schema::hasTable('list_folders')) {
            return [];
        }

        return DB::table('list_folders')->whereIn('id', $ids)->pluck('uuid', 'id')->all();
    }

    /**
     * @return array{count: int, computed_at: string, duration_ms: int}
     */
    private function storeCount(Segment $segment): array
    {
        $result = $this->count($this->rulesOf($segment));
        $segment->forceFill([
            'contact_count' => $result['count'],
            'count_computed_at' => now(),
            'cached_count' => $result['count'],
            'last_evaluated_at' => now(),
        ])->saveQuietly();

        return $result;
    }

    private function campaignIdsQuery(int $segmentId): Builder
    {
        return DB::table('campaigns')->select('id')->where('segment_id', $segmentId)
            ->union(DB::table('campaign_audiences')->select('campaign_id')->where('target_type', 'segment')->where('target_id', $segmentId));
    }

    private function folderId(?string $uuid, string $key = 'folder_id'): ?int
    {
        if ($uuid === null || $uuid === '') {
            return null;
        }

        $id = Schema::hasTable('list_folders')
            ? DB::table('list_folders')->where('uuid', $uuid)->where('kind', 'segment')->value('id')
            : null;

        if ($id === null) {
            throw ValidationException::withMessages([$key => 'Dossier de segments inconnu.']);
        }

        return (int) $id;
    }

    private function legacyListId(?string $uuid): ?int
    {
        if ($uuid === null || $uuid === '') {
            return null;
        }

        return RecipientList::query()->where('uuid', $uuid)->value('id')
            ?? throw ValidationException::withMessages(['recipient_list_id' => 'Liste inconnue.']);
    }
}
