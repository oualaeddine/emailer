<?php

namespace App\Modules\Recipients\Lists\Contracts;

use App\Modules\Recipients\Lists\Services\ListMembershipService as Implementation;
use App\Modules\Recipients\Models\RecipientList;
use Illuminate\Container\Attributes\Bind;

/**
 * docs/45-brevo-api-contracts.md §45.9 — the single writer of list
 * membership (WP-55, WP-57, WP-90, WP-91 must go through it).
 */
#[Bind(Implementation::class)]
interface ListMembershipService
{
    /**
     * @param  list<int>  $recipientIds  internal ids
     * @param  array<string, mixed>  $context  `importJobId`, `formId`
     * @return int number of contacts actually added
     */
    public function add(RecipientList $list, array $recipientIds, string $source, array $context = []): int;

    /**
     * @param  list<int>  $recipientIds
     * @return int number of contacts actually removed
     */
    public function remove(RecipientList $list, array $recipientIds, string $source = 'manual'): int;

    /** Recomputes the cached `contact_count` / `blocklisted_count`. */
    public function recount(RecipientList $list): void;

    /**
     * Resolves a ContactSelection (§45.3) to internal recipient ids.
     *
     * @param  array<string, mixed>  $selection
     * @return list<int>
     */
    public function resolveSelection(array $selection): array;
}
