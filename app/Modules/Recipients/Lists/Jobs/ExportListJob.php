<?php

namespace App\Modules\Recipients\Lists\Jobs;

use App\Modules\Recipients\Lists\Services\ListExportService;
use App\Modules\Recipients\Lists\Support\ListOperationStore;
use App\Modules\Recipients\Models\RecipientList;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/** docs/45 §45.5.6 — exports a list to CSV. */
class ExportListJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public readonly int $listId, public readonly string $operationId) {}

    public function handle(ListExportService $exports): void
    {
        $exports->build(RecipientList::query()->findOrFail($this->listId), $this->operationId);
    }

    public function failed(Throwable $e): void
    {
        app(ListOperationStore::class)->fail($this->operationId, $e->getMessage());
    }
}
