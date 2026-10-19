<?php

namespace App\Modules\Recipients\Lists\Jobs;

use App\Modules\Identity\Models\User;
use App\Modules\Recipients\Lists\Services\ListService;
use App\Modules\Recipients\Lists\Support\ListOperationStore;
use App\Modules\Recipients\Models\RecipientList;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/** docs/45 §45.5.6 — deletes a list with > 5000 members (type `list_delete`). */
class DeleteListJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly int $listId,
        public readonly bool $deleteContacts,
        public readonly string $operationId,
        public readonly ?int $actorId,
    ) {}

    public function handle(ListService $lists, ListOperationStore $ops): void
    {
        $ops->update($this->operationId, ['status' => 'running']);
        $list = RecipientList::query()->findOrFail($this->listId);
        $lists->delete($list, $this->deleteContacts, $this->actorId ? User::query()->find($this->actorId) : null);
        $ops->complete($this->operationId, []);
    }

    public function failed(Throwable $e): void
    {
        app(ListOperationStore::class)->fail($this->operationId, $e->getMessage());
    }
}
