<?php

namespace App\Modules\Suppression\Services;

use App\Domain\Enums\SuppressionReason;
use App\Modules\Suppression\Models\SuppressionEntry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * docs/23-suppression-list.md §23.5 — Suppression List Management UI
 * backend. This is the listing/lookup/removal layer around the grid;
 * {@see SuppressionManager} remains the single authoritative *upsert* path
 * (docs/23 §23.2/§23.3), used here for manual "Add" the same way it's used
 * by every automatic trigger (hard bounce, spam complaint, etc.) and by the
 * public unsubscribe flow (§23.4, {@see \App\Modules\Suppression\Http\Controllers\UnsubscribeController}).
 */
class SuppressionEntryService
{
    public function __construct(private readonly SuppressionManager $suppressionManager)
    {
    }

    /**
     * docs/23-suppression-list.md §23.5 — "searchable/filterable grid (by
     * reason, date added, source campaign)". `source campaign` filtering
     * isn't wired yet (no campaign linkage exists on this table beyond
     * `source_message_id`, and Campaigns is a separate work package); `q`
     * (email search) and `reason` cover the filters this work package's
     * task explicitly asks for.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return $this->applyFilters(SuppressionEntry::query()->with('addedBy'), $filters)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * docs/23-suppression-list.md §23.5 — Administration → Suppression
     * List → Add. Delegates to {@see SuppressionManager::suppress()},
     * which upserts (unique on `email` — a repeat add for an already
     * suppressed address is a no-op per §23.2) and marks any matching
     * `recipients` row suppressed.
     */
    public function create(string $email, SuppressionReason $reason, ?string $notes, ?int $addedBy): SuppressionEntry
    {
        $entry = $this->suppressionManager->suppress(
            email: $email,
            reason: $reason,
            notes: $notes,
            addedBy: $addedBy,
        );

        return $entry->load('addedBy');
    }

    public function findByUuid(string $uuid): SuppressionEntry
    {
        return SuppressionEntry::query()->with('addedBy')->where('uuid', $uuid)->firstOrFail();
    }

    /**
     * docs/23-suppression-list.md §23.5 — "Remove from suppression" (manual
     * override); §23.7 — permission-gated the same way for every reason,
     * confirmation handled by the caller
     * ({@see \App\Modules\Suppression\Http\Requests\DestroySuppressionEntryRequest}).
     */
    public function remove(SuppressionEntry $entry): void
    {
        $entry->delete();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        if (! empty($filters['reason'])) {
            $query->where('reason', $filters['reason']);
        }

        if (! empty($filters['q'])) {
            $query->where('email', 'like', '%'.$filters['q'].'%');
        }

        return $query;
    }
}
