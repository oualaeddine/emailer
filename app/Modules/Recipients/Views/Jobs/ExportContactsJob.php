<?php

namespace App\Modules\Recipients\Views\Jobs;

use App\Modules\Recipients\Views\Models\AsyncOperation;
use App\Modules\Recipients\Views\Services\ContactExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * docs/45 §45.9 — writes the CSV/XLSX of a `contacts_export` AsyncOperation.
 */
class ExportContactsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public readonly int $operationId) {}

    public function handle(ContactExportService $service): void
    {
        $operation = AsyncOperation::query()->find($this->operationId);

        if ($operation !== null) {
            $service->run($operation);
        }
    }
}
