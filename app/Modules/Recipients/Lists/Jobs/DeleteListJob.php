<?php

namespace App\Modules\Recipients\Lists\Jobs;

use App\Modules\Identity\Models\User;
use App\Modules\Recipients\Lists\Services\ListService;
use App\Modules\Recipients\Views\Contracts\AsyncOperations;
use App\Modules\Recipients\Views\Models\AsyncOperation;
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
        public readonly int $operationId,
        public readonly ?int $actorId,
    ) {}

    public function handle(ListService $lists, AsyncOperations $ops): void
    {
        $op = AsyncOperation::query()->findOrFail($this->operationId);
        $ops->progress($op, 0);
        $list = RecipientList::query()->findOrFail($this->listId);
        $lists->delete($list, $this->deleteContacts, $this->actorId ? User::query()->find($this->actorId) : null);
        $op->forceFill(['processed' => $op->total]);
        $ops->complete($op, []);
    }

    public function failed(Throwable $e): void
    {
        $op = AsyncOperation::query()->find($this->operationId);
        if ($op !== null) {
            app(AsyncOperations::class)->fail($op, $e->getMessage());
        }
    }
}
