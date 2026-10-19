<?php

namespace App\Modules\Recipients\Lists\Jobs;

use App\Modules\Identity\Models\User;
use App\Modules\Recipients\Lists\Contracts\ListMembershipService;
use App\Modules\Recipients\Models\RecipientList;
use App\Modules\Recipients\Views\Contracts\AsyncOperations;
use App\Modules\Recipients\Views\Models\AsyncOperation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/** docs/45 §45.5.6 — membership change from a ContactSelection (type `contacts_bulk`); the selection is resolved here. */
class BulkMembershipJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 900;

    /** @param  array<string, mixed>  $selection */
    public function __construct(
        public readonly int $listId,
        public readonly string $action,
        public readonly array $selection,
        public readonly int $operationId,
        public readonly ?int $userId = null,
    ) {}

    public function handle(ListMembershipService $members, AsyncOperations $ops): void
    {
        $op = AsyncOperation::query()->findOrFail($this->operationId);
        $ops->progress($op, 0);
        $list = RecipientList::query()->findOrFail($this->listId);
        $user = $this->userId !== null ? User::query()->find($this->userId) : null;
        $ids = $members->resolveSelection($this->selection, $user);

        $count = $this->action === 'add'
            ? $members->add($list, $ids, 'bulk')
            : $members->remove($list, $ids, 'bulk');

        $op->forceFill(['total' => count($ids), 'processed' => count($ids)]);
        $ops->complete($op, $this->action === 'add'
            ? ['added' => $count, 'removed' => 0, 'already' => count($ids) - $count, 'not_found' => []]
            : ['added' => 0, 'removed' => $count, 'already' => 0, 'not_found' => []]);
    }

    public function failed(Throwable $e): void
    {
        $op = AsyncOperation::query()->find($this->operationId);
        if ($op !== null) {
            app(AsyncOperations::class)->fail($op, $e->getMessage());
        }
    }
}
