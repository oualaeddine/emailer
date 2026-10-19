<?php

namespace App\Modules\Recipients\Views\Jobs;

use App\Modules\Recipients\Views\Models\AsyncOperation;
use App\Modules\Recipients\Views\Services\ContactBulkService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * docs/45 §45.9 — runs a `contacts_bulk` AsyncOperation (> 1000 contacts).
 */
class BulkRecipientActionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public readonly int $operationId) {}

    public function handle(ContactBulkService $service): void
    {
        $operation = AsyncOperation::query()->find($this->operationId);

        if ($operation !== null) {
            $service->runOperation($operation);
        }
    }
}
