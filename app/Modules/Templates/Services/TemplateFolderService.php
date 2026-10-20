<?php

namespace App\Modules\Templates\Services;

use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Identity\Models\User;
use App\Modules\Templates\Models\Template;
use App\Modules\Templates\Models\TemplateFolder;
use Illuminate\Database\Eloquent\Collection;

/** docs/45 §45.6.2 — `/template-folders`. */
class TemplateFolderService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @return Collection<int, TemplateFolder> */
    public function all(): Collection
    {
        return TemplateFolder::query()->orderBy('name')->get();
    }

    public function itemsCount(TemplateFolder $folder): int
    {
        return Template::query()->where('folder_id', $folder->id)->where('is_archived', false)->count();
    }

    public function create(string $name, User $actor): TemplateFolder
    {
        $folder = TemplateFolder::query()->create(['name' => $name, 'created_by' => $actor->id]);
        $this->audit->record('template_folder.created', $folder, $actor, null, ['name' => $name]);

        return $folder;
    }

    public function rename(TemplateFolder $folder, string $name, User $actor): TemplateFolder
    {
        $old = $folder->name;
        $folder->update(['name' => $name]);
        $this->audit->record('template_folder.updated', $folder, $actor, ['name' => $old], ['name' => $name]);

        return $folder;
    }

    /** Templates fall back to "no folder" (FK nullOnDelete). */
    public function delete(TemplateFolder $folder, User $actor): void
    {
        $name = $folder->name;
        $folder->delete();
        $this->audit->record('template_folder.deleted', $folder, $actor, ['name' => $name]);
    }

    /** Resolves a folder uuid (or null) to the internal id; the caller validated existence. */
    public function resolveId(?string $uuid): ?int
    {
        if ($uuid === null || $uuid === '') {
            return null;
        }

        return TemplateFolder::query()->where('uuid', $uuid)->value('id');
    }
}
