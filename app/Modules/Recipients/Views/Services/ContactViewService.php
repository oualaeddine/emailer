<?php

namespace App\Modules\Recipients\Views\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Recipients\Models\RecipientList;
use App\Modules\Recipients\Models\Segment;
use App\Modules\Recipients\Views\Models\ContactView;
use App\Modules\Recipients\Views\Support\ContactColumns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * docs/45 §45.5.4 — contact views CRUD. The system views `all` / `mine` are
 * virtual and can never be overwritten or deleted (409).
 */
class ContactViewService
{
    public const SYSTEM_IDS = ['all', 'mine'];

    public function __construct(private readonly ContactQueryService $queries) {}

    /**
     * @return array<string, mixed>
     */
    public function systemView(string $id): array
    {
        return [
            'id' => $id,
            'name' => $id === 'all' ? 'Tous les contacts' : 'Mes contacts',
            'is_system' => true,
            'is_shared' => false,
            'filters' => null,
            'list_id' => null,
            'segment_id' => null,
            'columns' => ContactColumns::defaults(),
            'sort' => ContactColumns::DEFAULT_SORT,
            'owner' => null,
            'created_at' => null,
            'updated_at' => null,
        ];
    }

    /**
     * @return Collection<int, ContactView>
     */
    public function visibleTo(User $user): Collection
    {
        return ContactView::query()
            ->with(['owner:id,uuid,name', 'list:id,uuid', 'segment:id,uuid'])
            ->where(fn (Builder $q) => $q->where('user_id', $user->id)->orWhere('is_shared', true))
            ->orderBy('position')->orderBy('id')
            ->get();
    }

    public function find(string $id, User $user): ContactView
    {
        $this->assertNotSystem($id);

        return ContactView::query()->with(['owner:id,uuid,name', 'list:id,uuid', 'segment:id,uuid'])
            ->where('uuid', $id)
            ->where(fn (Builder $q) => $q->where('user_id', $user->id)->orWhere('is_shared', true))
            ->firstOrFail();
    }

    public function assertNotSystem(string $id): void
    {
        if (in_array($id, self::SYSTEM_IDS, true)) {
            throw new HttpResponseException(response()->json([
                'message' => 'Les vues par défaut ne peuvent pas être modifiées : enregistrez-les comme nouvelle vue.',
            ], 409));
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, array $data): ContactView
    {
        $view = ContactView::query()->create($this->attributes($data) + [
            'user_id' => $user->id,
            'columns' => ContactColumns::defaults(),
            'is_shared' => false,
        ]);

        return $view->load(['owner:id,uuid,name', 'list:id,uuid', 'segment:id,uuid']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ContactView $view, array $data): ContactView
    {
        $view->fill($this->attributes($data))->save();

        return $view->load(['owner:id,uuid,name', 'list:id,uuid', 'segment:id,uuid']);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        $out = [];

        foreach (['name', 'is_shared'] as $key) {
            if (array_key_exists($key, $data)) {
                $out[$key] = $data[$key];
            }
        }

        if (array_key_exists('filters', $data)) {
            if (! empty($data['filters'])) {
                $this->queries->validateRules($data['filters']);
            }
            $out['filters'] = empty($data['filters']) ? null : $data['filters'];
        }

        if (array_key_exists('columns', $data)) {
            $out['columns'] = ContactColumns::normalize($data['columns'] ?? []);
        }

        if (array_key_exists('sort', $data)) {
            if ($data['sort'] !== null) {
                ContactColumns::sortExpression($data['sort']);
            }
            $out['sort'] = $data['sort'];
        }

        if (array_key_exists('list_id', $data)) {
            $out['list_id'] = $data['list_id'] === null ? null
                : (RecipientList::query()->where('uuid', $data['list_id'])->value('id')
                    ?? throw ValidationException::withMessages(['list_id' => ['Liste introuvable.']]));
        }

        if (array_key_exists('segment_id', $data)) {
            $out['segment_id'] = $data['segment_id'] === null ? null
                : (Segment::query()->where('uuid', $data['segment_id'])->value('id')
                    ?? throw ValidationException::withMessages(['segment_id' => ['Segment introuvable.']]));
        }

        return $out;
    }
}
