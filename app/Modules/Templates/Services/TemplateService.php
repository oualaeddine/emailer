<?php

namespace App\Modules\Templates\Services;

use App\Modules\Composer\Models\Draft;
use App\Modules\Identity\Models\User;
use App\Modules\Templates\Events\TemplateSaved;
use App\Modules\Templates\Models\Template;
use App\Modules\Templates\Models\TemplateFolder;
use App\Modules\Templates\Models\TemplateVersion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
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

    /**
     * @param  array<string, mixed>  $extra  v2 attributes (editor_type, scope, design_json, subject…)
     */
    public function create(User $author, string $name, string $htmlContent, ?string $category = null, array $extra = []): Template
    {
        $template = DB::transaction(function () use ($author, $name, $htmlContent, $category, $extra): Template {
            $template = Template::query()->create([
                ...$extra,
                'name' => $name,
                'category' => $category,
                'html_content' => $htmlContent,
                'created_by' => $author->id,
            ]);

            $this->saveVersion($template, $author, 'Version initiale');

            return $template;
        });

        TemplateSaved::dispatch($template->id);

        return $template;
    }

    /**
     * docs/12-template-system.md §12.4 — every save creates a version.
     */
    public function update(Template $template, User $author, string $htmlContent, ?string $changeNote = null): Template
    {
        $fresh = DB::transaction(function () use ($template, $author, $htmlContent, $changeNote): Template {
            $template->update(['html_content' => $htmlContent]);
            $this->saveVersion($template, $author, $changeNote);

            return $template->fresh();
        });

        TemplateSaved::dispatch($fresh->id);

        return $fresh;
    }

    /**
     * docs/45 §45.4.4 — duplicate; works on gallery templates (the copy lands
     * in "Mes modèles": `is_gallery = false`).
     *
     * @param  array{name?: string|null, scope?: string|null, folder_id?: int|null}  $overrides
     */
    public function duplicate(Template $source, User $author, array $overrides = []): Template
    {
        $copy = DB::transaction(function () use ($source, $author, $overrides): Template {
            $copy = Template::query()->create([
                'name' => $overrides['name'] ?? 'Copie de '.$source->name,
                'category' => $source->category,
                'html_content' => $source->html_content,
                'design_json' => $source->design_json,
                'editor_type' => $source->editor_type,
                'scope' => $overrides['scope'] ?? $source->scope,
                'subject' => $source->subject,
                'folder_id' => $overrides['folder_id'] ?? null,
                'is_gallery' => false,
                'gallery_category' => null,
                'created_by' => $author->id,
            ]);

            $this->saveVersion($copy, $author, 'Dupliqué depuis « '.$source->name.' »');

            return $copy;
        });

        TemplateSaved::dispatch($copy->id);

        return $copy;
    }

    private const SORTABLE = ['updated_at', 'name', 'created_at', 'usage_count'];

    /**
     * docs/45 §45.4.4 — `TemplateListQuery`.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Template>
     */
    public function search(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        /** @var Builder<Template> $query */
        $query = Template::query();

        if (! empty($filters['scope'])) {
            $query->where('scope', $filters['scope']);
        }
        if (($filters['folder_id'] ?? null) === 'none') {
            $query->whereNull('folder_id');
        } elseif (! empty($filters['folder_id'])) {
            $query->whereIn('folder_id', TemplateFolder::query()->where('uuid', $filters['folder_id'])->select('id'));
        }
        if (! empty($filters['q'])) {
            $needle = '%'.addcslashes((string) $filters['q'], '%_\\').'%';
            $query->where('name', 'like', $needle);
        }
        if (array_key_exists('is_gallery', $filters) && $filters['is_gallery'] !== null) {
            $query->where('is_gallery', (bool) $filters['is_gallery']);
        }
        if (! empty($filters['editor_type'])) {
            $query->where('editor_type', $filters['editor_type']);
        }
        $query->where('is_archived', (bool) ($filters['is_archived'] ?? false));
        if (! empty($filters['category'])) {
            $query->where(fn (Builder $q) => $q->where('category', $filters['category'])->orWhere('gallery_category', $filters['category']));
        }

        $sort = (string) ($filters['sort'] ?? '-updated_at');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $field = ltrim($sort, '-');
        if (! in_array($field, self::SORTABLE, true)) {
            throw ValidationException::withMessages(['sort' => ['Tri non pris en charge.']]);
        }
        if ($field === 'usage_count') {
            $query->orderBy(
                Draft::query()->selectRaw('count(*)')->whereColumn('drafts.template_id', 'templates.id'),
                $direction,
            );
        } else {
            $query->orderBy($field, $direction);
        }

        return $query->orderBy('id')->paginate($perPage);
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
            'design_json' => $template->design_json,
            'editor_type' => $template->editor_type,
        ]);
    }

    public function archive(Template $template): Template
    {
        $template->update(['is_archived' => true]);

        return $template;
    }

    public function unarchive(Template $template): Template
    {
        $template->update(['is_archived' => false]);

        return $template;
    }

    /**
     * docs/45 §45.6.2 — restore: the restored HTML becomes a NEW version.
     */
    public function restoreVersion(Template $template, TemplateVersion $version, User $author): Template
    {
        $fresh = DB::transaction(function () use ($template, $version, $author): Template {
            // A version without a design snapshot (pre-snapshot or plain HTML) restores as an HTML template.
            $hasDesign = $version->design_json !== null;
            $template->update([
                'html_content' => $version->html_content,
                'design_json' => $hasDesign ? $version->design_json : null,
                'editor_type' => $hasDesign ? ($version->editor_type ?: $template->editor_type) : 'html',
            ]);
            $this->saveVersion($template, $author, 'Restauré depuis la version '.$version->version_number);

            return $template->fresh();
        });

        TemplateSaved::dispatch($fresh->id);

        return $fresh;
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
