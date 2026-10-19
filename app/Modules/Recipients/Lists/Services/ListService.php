<?php

namespace App\Modules\Recipients\Lists\Services;

use App\Domain\Enums\RecipientListType;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Identity\Models\User;
use App\Modules\Recipients\Events\RecipientDeleted;
use App\Modules\Recipients\Lists\Models\ListFolder;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Recipients\Models\RecipientList;
use App\Modules\Recipients\Services\RecipientListService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * docs/45 §45.5.6 — list CRUD on top of the legacy RecipientListService.
 */
class ListService
{
    public const ASYNC_DELETE_THRESHOLD = 5000;

    private const SORTS = ['name', 'created_at', 'contact_count', 'last_import_at'];

    public function __construct(
        private readonly RecipientListService $legacy,
        private readonly AuditLogger $audit,
    ) {}

    /** @param  array<string, mixed>  $filters */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $sort = (string) ($filters['sort'] ?? 'name');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $field = ltrim($sort, '-');
        if (! in_array($field, self::SORTS, true)) {
            throw ValidationException::withMessages(['sort' => 'Tri non autorisé.']);
        }

        $query = RecipientList::query()->with(['createdBy', 'segment']);

        // Dynamic lists are legacy: segments replace them (docs/45 §45.5.6).
        if (! filter_var($filters['include_legacy'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $query->where('type', '!=', RecipientListType::DynamicSegment->value);
        }

        if (($q = trim((string) ($filters['q'] ?? ''))) !== '') {
            $query->where('name', 'like', '%'.$q.'%');
        }
        if (isset($filters['folder_id']) && $filters['folder_id'] !== '') {
            $filters['folder_id'] === 'none'
                ? $query->whereNull('folder_id')
                : $query->whereIn('folder_id', ListFolder::query()->where('uuid', $filters['folder_id'])->select('id'));
        }

        return $query->orderBy($field, $direction)->orderBy('id')
            ->paginate(min((int) ($filters['per_page'] ?? 25), 100));
    }

    /** @param  array<string, mixed>  $data */
    public function create(array $data, User $actor): RecipientList
    {
        $list = $this->legacy->createStatic($data['name'], $data['description'] ?? null, $actor);

        if (! empty($data['folder_id'])) {
            $list->forceFill(['folder_id' => $this->folderId($data['folder_id'])])->save();
        }

        $this->audit->record('list.created', $list, $actor, null, ['name' => $list->name]);

        return $list->fresh(['createdBy', 'segment']);
    }

    /** @param  array<string, mixed>  $data */
    public function update(RecipientList $list, array $data, User $actor): RecipientList
    {
        $old = ['name' => $list->name, 'description' => $list->description, 'folder_id' => $list->folder_id];

        if (array_key_exists('name', $data)) {
            $list->name = $data['name'];
        }
        if (array_key_exists('description', $data)) {
            $list->description = $data['description'];
        }
        if (array_key_exists('folder_id', $data)) {
            $list->folder_id = $data['folder_id'] === null ? null : $this->folderId($data['folder_id']);
        }
        $list->save();

        $this->audit->record('list.updated', $list, $actor, $old, [
            'name' => $list->name, 'description' => $list->description, 'folder_id' => $list->folder_id,
        ]);

        return $list->fresh(['createdBy', 'segment']);
    }

    /** True when a scheduled/running campaign targets the list (§45.5.6 409). */
    public function isInUse(RecipientList $list): bool
    {
        $active = ['scheduled', 'running'];

        return DB::table('campaign_audiences as a')
            ->join('campaigns as c', 'c.id', '=', 'a.campaign_id')
            ->where('a.target_type', 'list')
            ->where('a.target_id', $list->id)
            ->whereIn('c.status', $active)
            ->exists()
            || DB::table('campaigns')->where('recipient_list_id', $list->id)->whereIn('status', $active)->exists();
    }

    public function assertNotInUse(RecipientList $list): void
    {
        if ($this->isInUse($list)) {
            throw new ConflictHttpException(
                'Cette liste est utilisée par une campagne programmée, un formulaire ou une automatisation.',
            );
        }
    }

    public function memberCount(RecipientList $list): int
    {
        return (int) DB::table('recipient_list_recipient')->where('recipient_list_id', $list->id)->count();
    }

    public function delete(RecipientList $list, bool $deleteContacts, ?User $actor): void
    {
        DB::transaction(function () use ($list, $deleteContacts): void {
            if ($deleteContacts) {
                // Per-model deletes + RecipientDeleted so the other lists' counters get recounted.
                DB::table('recipient_list_recipient')->where('recipient_list_id', $list->id)->orderBy('recipient_id')
                    ->select('recipient_id')->chunk(1000, function ($rows): void {
                        Recipient::query()->whereIn('id', $rows->pluck('recipient_id'))->get()
                            ->each(function (Recipient $recipient): void {
                                $recipient->delete();
                                event(new RecipientDeleted($recipient->id, $recipient->email));
                            });
                    });
            }
            $list->delete();
        });

        $this->audit->record('list.deleted', $list, $actor, ['name' => $list->name], ['delete_contacts' => $deleteContacts]);
    }

    private function folderId(string $uuid): int
    {
        $id = ListFolder::query()->where('uuid', $uuid)->where('kind', ListFolder::KIND_LIST)->value('id');
        if ($id === null) {
            throw ValidationException::withMessages(['folder_id' => 'Dossier introuvable.']);
        }

        return $id;
    }

    /** @return Builder<RecipientList> */
    public function queryForFolder(ListFolder $folder): Builder
    {
        return RecipientList::query()->where('folder_id', $folder->id);
    }
}
