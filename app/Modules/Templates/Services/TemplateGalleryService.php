<?php

namespace App\Modules\Templates\Services;

use App\Modules\Templates\Models\Template;
use Illuminate\Database\Eloquent\Collection;

/** docs/45 §45.6.2 — read side of the built-in gallery (`is_gallery = true`). */
class TemplateGalleryService
{
    /**
     * @return Collection<int, Template>
     */
    public function list(?string $category = null): Collection
    {
        return Template::query()
            ->where('is_gallery', true)
            ->where('is_archived', false)
            ->when($category !== null, fn ($q) => $q->where('gallery_category', $category))
            ->orderBy('gallery_category')
            ->orderBy('name')
            ->get();
    }

    /**
     * Counts over the whole gallery, independent of the `category` filter.
     *
     * @return list<array{key: string, count: int}>
     */
    public function categoryCounts(): array
    {
        return Template::query()
            ->where('is_gallery', true)
            ->where('is_archived', false)
            ->whereNotNull('gallery_category')
            ->selectRaw('gallery_category as `key`, count(*) as aggregate')
            ->groupBy('gallery_category')
            ->orderBy('gallery_category')
            ->get()
            ->map(fn ($row) => ['key' => (string) $row->key, 'count' => (int) $row->aggregate])
            ->all();
    }
}
