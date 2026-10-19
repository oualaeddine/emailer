<?php

namespace App\Modules\Recipients\Lists\Jobs;

use App\Modules\Recipients\Lists\Contracts\ListMembershipService;
use App\Modules\Recipients\Lists\Support\ListOperationStore;
use App\Modules\Recipients\Models\RecipientList;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/** docs/45 §45.5.6 — membership change from a ContactSelection > 1000 (type `contacts_bulk`). */
class BulkMembershipJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    /** @param  array<string, mixed>  $selection */
    public function __construct(
        public readonly int $listId,
        public readonly string $action,
        public readonly array $selection,
        public readonly string $operationId,
    ) {}

    public function handle(ListMembershipService $members, ListOperationStore $ops): void
    {
        $ops->update($this->operationId, ['status' => 'running']);
        $list = RecipientList::query()->findOrFail($this->listId);
        $ids = $members->resolveSelection($this->selection);

        $count = $this->action === 'add'
            ? $members->add($list, $ids, 'bulk')
            : $members->remove($list, $ids, 'bulk');

        $ops->update($this->operationId, ['total' => count($ids), 'processed' => count($ids)]);
        $ops->complete($this->operationId, $this->action === 'add'
            ? ['added' => $count, 'removed' => 0, 'already' => count($ids) - $count, 'not_found' => []]
            : ['added' => 0, 'removed' => $count, 'already' => 0, 'not_found' => []]);
    }

    public function failed(Throwable $e): void
    {
        app(ListOperationStore::class)->fail($this->operationId, $e->getMessage());
    }
}
