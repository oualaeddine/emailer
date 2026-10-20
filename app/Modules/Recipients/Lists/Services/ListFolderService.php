<?php

namespace App\Modules\Recipients\Lists\Services;

use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Identity\Models\User;
use App\Modules\Recipients\Lists\Models\ListFolder;
use App\Modules\Recipients\Models\RecipientList;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/** docs/45 §45.5.6 — `/list-folders` (kinds list|segment). */
class ListFolderService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly ListService $lists,
    ) {}

    /** @return Collection<int, ListFolder> */
    public function all(string $kind): Collection
    {
        return ListFolder::query()->where('kind', $kind)->orderBy('name')->get();
    }

    public function itemsCount(ListFolder $folder): int
    {
        if ($folder->kind === ListFolder::KIND_LIST) {
            return RecipientList::query()->where('folder_id', $folder->id)->count();
        }

        // Segment folders: `segments.folder_id` is added by WP-54.
        return Schema::hasColumn('segments', 'folder_id')
            ? (int) DB::table('segments')->where('folder_id', $folder->id)->count()
            : 0;
    }

    public function create(string $name, string $kind, User $actor): ListFolder
    {
        $folder = ListFolder::query()->create(['name' => $name, 'kind' => $kind, 'created_by' => $actor->id]);
        $this->audit->record('list_folder.created', $folder, $actor, null, ['name' => $name, 'kind' => $kind]);

        return $folder;
    }

    public function rename(ListFolder $folder, string $name, User $actor): ListFolder
    {
        $old = $folder->name;
        $folder->update(['name' => $name]);
        $this->audit->record('list_folder.updated', $folder, $actor, ['name' => $old], ['name' => $name]);

        return $folder;
    }

    public function delete(ListFolder $folder, bool $deleteChildren, User $actor): void
    {
        DB::transaction(function () use ($folder, $deleteChildren, $actor): void {
            if ($deleteChildren && $folder->kind === ListFolder::KIND_LIST) {
                $children = $this->lists->queryForFolder($folder)->get();
                foreach ($children as $child) {
                    if ($this->lists->isInUse($child)) {
                        throw new ConflictHttpException(
                            'Cette liste est utilisée par une campagne programmée, un formulaire ou une automatisation.',
                        );
                    }
                }
                foreach ($children as $child) {
                    $this->lists->delete($child, false, $actor);
                }
            }
            // Otherwise children fall back to "no folder" (FK nullOnDelete).
            $folder->delete();
        });

        $this->audit->record('list_folder.deleted', $folder, $actor, ['name' => $folder->name], ['delete_children' => $deleteChildren]);
    }
}
