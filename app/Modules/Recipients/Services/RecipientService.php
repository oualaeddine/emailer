<?php

namespace App\Modules\Recipients\Services;

use App\Domain\Enums\RecipientStatus;
use App\Modules\Recipients\DataTransferObjects\CreateRecipientData;
use App\Modules\Recipients\Exceptions\RecipientAlreadyExistsException;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Recipients\Models\RecipientNote;
use App\Modules\Recipients\Models\Tag;
use App\Modules\Identity\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * docs/13-recipient-management.md — Recipient Management.
 */
class RecipientService
{
    /**
     * docs/29-api-specification.md §29.4 — the direct manual-create API:
     * "409 if email exists (returns existing recipient link suggestion)".
     *
     * @throws RecipientAlreadyExistsException
     */
    public function create(CreateRecipientData $data): Recipient
    {
        $existing = Recipient::query()->where('email', $data->email)->first();

        if ($existing !== null) {
            throw new RecipientAlreadyExistsException($existing);
        }

        return Recipient::query()->create([
            'email' => $data->email,
            'first_name' => $data->firstName,
            'last_name' => $data->lastName,
            'company_name' => $data->companyName,
            'pagejaunes_company_cache_id' => $data->pageJaunesCompanyCacheId,
            'source' => $data->source->value,
            'status' => RecipientStatus::Active->value,
            'custom_fields' => $data->customFields,
        ]);
    }

    /**
     * docs/13-recipient-management.md §13.4 — Duplicate Detection: hard
     * uniqueness on email; bulk import/PageJaunes paths merge rather than
     * duplicate or conflict ("first source wins for `source`, richest
     * non-null wins per field") — unlike {@see self::create()} above,
     * which is for the direct manual-create API and conflicts instead.
     */
    public function createOrMerge(CreateRecipientData $data): Recipient
    {
        return DB::transaction(function () use ($data): Recipient {
            $existing = Recipient::query()->where('email', $data->email)->first();

            if ($existing === null) {
                return Recipient::query()->create([
                    'email' => $data->email,
                    'first_name' => $data->firstName,
                    'last_name' => $data->lastName,
                    'company_name' => $data->companyName,
                    'pagejaunes_company_cache_id' => $data->pageJaunesCompanyCacheId,
                    'source' => $data->source->value,
                    'status' => RecipientStatus::Active->value,
                    'custom_fields' => $data->customFields,
                ]);
            }

            $existing->first_name ??= $data->firstName;
            $existing->last_name ??= $data->lastName;
            $existing->company_name ??= $data->companyName;
            $existing->pagejaunes_company_cache_id ??= $data->pageJaunesCompanyCacheId;
            $existing->custom_fields = array_merge($data->customFields, $existing->custom_fields ?? []);
            // `source` is deliberately left untouched — first source wins.
            $existing->save();

            return $existing;
        });
    }

    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return $this->applyFilters(Recipient::query()->with('tags'), $filters)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        if (! empty($filters['q'])) {
            $q = $filters['q'];
            $query->where(function (Builder $inner) use ($q): void {
                $inner->where('email', 'like', "%{$q}%")
                    ->orWhere('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%")
                    ->orWhere('company_name', 'like', "%{$q}%");
            });
        }

        if (! empty($filters['source'])) {
            $query->where('source', $filters['source']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['tag_id'])) {
            $query->whereHas('tags', fn (Builder $t) => $t->where('tags.id', $filters['tag_id']));
        }

        return $query;
    }

    public function findByUuid(string $uuid): Recipient
    {
        return Recipient::query()->with(['tags', 'pageJaunesCompany', 'notes.user'])
            ->where('uuid', $uuid)->firstOrFail();
    }

    public function syncTags(Recipient $recipient, array $tagIds): Recipient
    {
        $recipient->tags()->sync($tagIds);

        return $recipient->fresh('tags');
    }

    public function addNote(Recipient $recipient, string $body, User $author): RecipientNote
    {
        return $recipient->notes()->create(['body' => $body, 'user_id' => $author->id]);
    }
}
