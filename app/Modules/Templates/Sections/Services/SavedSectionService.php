<?php

namespace App\Modules\Templates\Sections\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Templates\Sections\Models\SavedSection;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * docs/45-brevo-api-contracts.md §45.7.1 — saved sections of the
 * EmailBuilder *Enregistrés* tab (shared by the account).
 */
class SavedSectionService
{
    /** `GET /saved-sections` is a small non-paginated list (§45.1 `DataList`). */
    public const LIST_LIMIT = 200;

    /**
     * @return Collection<int, SavedSection>
     */
    public function list(?string $q = null): Collection
    {
        $query = SavedSection::query()->with('createdBy')->latest('id');

        $term = trim((string) $q);
        if ($term !== '') {
            $query->where('name', 'like', '%'.addcslashes($term, '%_\\').'%');
        }

        return $query->limit(self::LIST_LIMIT)->get();
    }

    /**
     * @param  array<string, mixed>  $component
     */
    public function create(string $name, string $mjml, array $component, ?User $user): SavedSection
    {
        $section = SavedSection::query()->create([
            'name' => trim($name),
            'mjml' => trim($mjml),
            'component' => $this->withoutIds($component),
            'created_by' => $user?->id,
        ]);

        return $section->load('createdBy');
    }

    public function rename(SavedSection $section, string $name): SavedSection
    {
        $section->forceFill(['name' => trim($name)])->save();

        return $section->load('createdBy');
    }

    public function delete(SavedSection $section): void
    {
        if ($section->thumbnail_path !== null) {
            Storage::disk('public')->delete($section->thumbnail_path);
        }

        $section->delete();
    }

    /**
     * GrapesJS component JSON without element ids, so inserting the same
     * section twice never duplicates an `id` attribute.
     *
     * @param  array<string|int, mixed>  $node
     * @return array<string|int, mixed>
     */
    private function withoutIds(array $node): array
    {
        if (isset($node['attributes']) && is_array($node['attributes'])) {
            unset($node['attributes']['id']);
        }

        foreach ($node as $key => $value) {
            if (is_array($value)) {
                $node[$key] = $this->withoutIds($value);
            }
        }

        return $node;
    }
}
