<?php

namespace App\Modules\Recipients\Lists\Services;

use App\Modules\Recipients\Lists\Contracts\ListMembershipService as Contract;
use App\Modules\Recipients\Lists\Events\RecipientsAddedToList;
use App\Modules\Recipients\Lists\Events\RecipientsRemovedFromList;
use App\Modules\Identity\Models\User;
use App\Modules\Recipients\Models\RecipientList;
use App\Modules\Recipients\Views\Services\ContactQueryService;
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
            'blocklisted_count' => (clone $base)->whereNotNull('r.blocklisted_at')->count(),
        ];

        DB::table('recipient_lists')->where('id', $list->id)->update($counts);
        $list->forceFill($counts)->syncOriginal();
    }

    /**
     * Resolves a ContactSelection (§45.3) to internal recipient ids through the
     * shared {@see ContactQueryService} (filters, segment, view, scope, status,
     * source and the `MAX_ALL_MATCHING` cap are applied there).
     *
     * @param  array<string, mixed>  $selection
     * @return list<int>
     */
    public function resolveSelection(array $selection, ?User $user = null): array
    {
        return app(ContactQueryService::class)->selectionQuery($selection, $user)->pluck('recipients.id')->all();
    }

    /** @param  array<string, mixed>  $selection */
    public function countSelection(array $selection, ?User $user = null): int
    {
        return app(ContactQueryService::class)->selectionQuery($selection, $user)->count();
    }
}
