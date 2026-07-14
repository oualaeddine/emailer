<?php

namespace App\Modules\Templates\Services;

use App\Modules\Composer\Models\Draft;
use App\Modules\Identity\Models\User;
use App\Modules\Templates\Models\Template;
use App\Modules\Templates\Models\TemplateVersion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * docs/12-template-system.md — Template System.
 */
class TemplateService
{
    /**
     * @return Collection<int, Template>
     */
    public function library(?string $category = null, bool $includeArchived = false): Collection
    {
        $query = Template::query()->orderBy('name');

        if ($category !== null) {
            $query->where('category', $category);
        }

        if (! $includeArchived) {
            $query->where('is_archived', false);
        }

        return $query->get();
    }

    public function create(User $author, string $name, string $htmlContent, ?string $category = null): Template
    {
        return DB::transaction(function () use ($author, $name, $htmlContent, $category): Template {
            $template = Template::query()->create([
                'name' => $name,
                'category' => $category,
                'html_content' => $htmlContent,
                'created_by' => $author->id,
            ]);

            $this->saveVersion($template, $author, 'Version initiale');

            return $template;
        });
    }

    /**
     * docs/12-template-system.md §12.4 — every save creates a version.
     */
    public function update(Template $template, User $author, string $htmlContent, ?string $changeNote = null): Template
    {
        return DB::transaction(function () use ($template, $author, $htmlContent, $changeNote): Template {
            $template->update(['html_content' => $htmlContent]);
            $this->saveVersion($template, $author, $changeNote);

            return $template->fresh();
        });
    }

    public function saveVersion(Template $template, User $author, ?string $changeNote = null): TemplateVersion
    {
        $nextVersion = ($template->versions()->max('version_number') ?? 0) + 1;

        return TemplateVersion::query()->create([
            'template_id' => $template->id,
            'version_number' => $nextVersion,
            'html_content' => $template->html_content,
            'created_by' => $author->id,
            'change_note' => $changeNote,
        ]);
    }

    public function archive(Template $template): Template
    {
        $template->update(['is_archived' => true]);

        return $template;
    }

    /**
     * docs/12-template-system.md §12.2 — "usage count" for the library gallery.
     */
    public function usageCount(Template $template): int
    {
        return Draft::query()->where('template_id', $template->id)->count();
    }

    public function canBeDeleted(Template $template): bool
    {
        return $this->usageCount($template) === 0;
    }
}
