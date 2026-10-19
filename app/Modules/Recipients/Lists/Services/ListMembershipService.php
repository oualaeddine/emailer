<?php

namespace App\Modules\Recipients\Lists\Services;

use App\Modules\Recipients\Lists\Contracts\ListMembershipService as Contract;
use App\Modules\Recipients\Lists\Events\RecipientsAddedToList;
use App\Modules\Recipients\Lists\Events\RecipientsRemovedFromList;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Recipients\Models\RecipientList;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * docs/45 §45.9 / §45.5.6 — list membership writer + `contact_count` cache.
 * The count is recomputed from the pivot after each write (exact, idempotent).
 */
class ListMembershipService implements Contract
{
    private const CHUNK = 1000;

    public function add(RecipientList $list, array $recipientIds, string $source, array $context = []): int
    {
        $ids = array_values(array_unique(array_map('intval', $recipientIds)));
        $added = [];
        $now = now();

        foreach (array_chunk($ids, self::CHUNK) as $chunk) {
            $existing = DB::table('recipient_list_recipient')
                ->where('recipient_list_id', $list->id)
                ->whereIn('recipient_id', $chunk)
                ->pluck('recipient_id')
                ->all();
            $new = array_values(array_diff($chunk, $existing));
            if ($new === []) {
                continue;
            }
            DB::table('recipient_list_recipient')->insertOrIgnore(array_map(fn (int $id): array => [
                'recipient_list_id' => $list->id,
                'recipient_id' => $id,
                'added_at' => $now,
                'source' => $source,
            ], $new));
            array_push($added, ...$new);
        }

        if ($added !== []) {
            $this->recount($list);
            RecipientsAddedToList::dispatch(
                $list->id,
                $added,
                $source,
                $context['importJobId'] ?? null,
                $context['formId'] ?? null,
            );
        }

        return count($added);
    }

    public function remove(RecipientList $list, array $recipientIds, string $source = 'manual'): int
    {
        $ids = array_values(array_unique(array_map('intval', $recipientIds)));
        $removed = [];

        foreach (array_chunk($ids, self::CHUNK) as $chunk) {
            $present = DB::table('recipient_list_recipient')
                ->where('recipient_list_id', $list->id)
                ->whereIn('recipient_id', $chunk)
                ->pluck('recipient_id')
                ->all();
            if ($present === []) {
                continue;
            }
            DB::table('recipient_list_recipient')
                ->where('recipient_list_id', $list->id)
                ->whereIn('recipient_id', $present)
                ->delete();
            array_push($removed, ...$present);
        }

        if ($removed !== []) {
            $this->recount($list);
            RecipientsRemovedFromList::dispatch($list->id, $removed, $source);
        }

        return count($removed);
    }

    public function recount(RecipientList $list): void
    {
        $base = DB::table('recipient_list_recipient as p')
            ->join('recipients as r', 'r.id', '=', 'p.recipient_id')
            ->where('p.recipient_list_id', $list->id)
            ->whereNull('r.deleted_at');

        $counts = [
            'contact_count' => (clone $base)->count(),
            'blocklisted_count' => (clone $base)->where('r.status', 'suppressed')->count(),
        ];

        DB::table('recipient_lists')->where('id', $list->id)->update($counts);
        $list->forceFill($counts)->syncOriginal();
    }

    /**
     * Resolves a ContactSelection (§45.3) to internal recipient ids.
     * `all_matching` supports `q`, `list_id` and `exclude_ids` (the full
     * filter/segment/view resolution belongs to WP-57).
     *
     * @param  array<string, mixed>  $selection
     * @return list<int>
     */
    public function resolveSelection(array $selection): array
    {
        if (isset($selection['ids'])) {
            return Recipient::query()->whereIn('uuid', $selection['ids'])->pluck('id')->all();
        }

        $criteria = $selection['all_matching'] ?? [];
        $query = Recipient::query()
            ->when($criteria['q'] ?? null, fn (Builder $q, string $term) => $q->where(function (Builder $w) use ($term): void {
                $like = '%'.$term.'%';
                $w->where('email', 'like', $like)->orWhere('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)->orWhere('company_name', 'like', $like);
            }))
            ->when($criteria['list_id'] ?? null, fn (Builder $q, string $listUuid) => $q->whereIn(
                'recipients.id',
                DB::table('recipient_list_recipient as p')
                    ->join('recipient_lists as l', 'l.id', '=', 'p.recipient_list_id')
                    ->where('l.uuid', $listUuid)->select('p.recipient_id'),
            ));

        if (! empty($selection['exclude_ids'])) {
            $query->whereNotIn('uuid', $selection['exclude_ids']);
        }

        return $query->pluck('id')->all();
    }
}
