<?php

namespace App\Modules\Recipients\Lists\Listeners;

use App\Modules\Recipients\Events\RecipientDeleted;
use App\Modules\Recipients\Lists\Contracts\ListMembershipService;
use App\Modules\Recipients\Models\RecipientList;
use App\Modules\Suppression\Listeners\RecipientBlocklistChanged;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;

/**
 * docs/45 §45.5.6 — keeps `contact_count` / `blocklisted_count` honest when a
 * contact is deleted or its blocklist flag flips outside the membership service.
 */
class RecountListsForRecipient implements ShouldQueue
{
    public function __construct(private readonly ListMembershipService $members) {}

    public function handle(RecipientDeleted|RecipientBlocklistChanged $event): void
    {
        $listIds = DB::table('recipient_list_recipient')->where('recipient_id', $event->recipientId)->pluck('recipient_list_id');

        RecipientList::query()->whereIn('id', $listIds)->get()->each(fn (RecipientList $list) => $this->members->recount($list));
    }
}
