<?php

namespace App\Modules\Recipients\Services;

use App\Domain\Enums\RecipientListType;
use App\Modules\Identity\Models\User;
use App\Modules\Recipients\Models\RecipientList;
use App\Modules\Recipients\Models\Segment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * docs/13-recipient-management.md §13.7 — Lists vs. Segments.
 */
class RecipientListService
{
    /**
     * @return Collection<int, RecipientList>
     */
    public function all(): Collection
    {
        return RecipientList::query()->with('segment')->orderBy('name')->get();
    }

    public function createStatic(string $name, ?string $description, User $createdBy): RecipientList
    {
        return RecipientList::query()->create([
            'name' => $name,
            'description' => $description,
            'type' => RecipientListType::Static->value,
            'created_by' => $createdBy->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $rules
     */
    public function createDynamicSegment(string $name, ?string $description, array $rules, User $createdBy): RecipientList
    {
        return DB::transaction(function () use ($name, $description, $rules, $createdBy): RecipientList {
            $list = RecipientList::query()->create([
                'name' => $name,
                'description' => $description,
                'type' => RecipientListType::DynamicSegment->value,
                'created_by' => $createdBy->id,
            ]);

            Segment::query()->create([
                'recipient_list_id' => $list->id,
                'rules' => $rules,
            ]);

            return $list->fresh('segment');
        });
    }

    public function addRecipients(RecipientList $list, array $recipientIds): void
    {
        $now = now();
        $list->recipients()->syncWithoutDetaching(
            collect($recipientIds)->mapWithKeys(fn ($id) => [$id => ['added_at' => $now]])->all(),
        );
    }
}
