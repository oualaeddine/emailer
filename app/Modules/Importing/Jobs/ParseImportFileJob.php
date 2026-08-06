<?php

namespace App\Modules\Importing\Jobs;

use App\Domain\Enums\ImportJobStatus;
use App\Domain\Enums\ImportSourceType;
use App\Modules\Importing\Models\ImportJob;
use App\Modules\Importing\Services\CsvImportService;
use App\Modules\Importing\Services\ExcelImportService;
use App\Modules\Importing\Services\ImportJobService;
use App\Modules\Importing\Services\ImportValidationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * docs/30-background-jobs.md — `ProcessImportFileJob` (Parse + Validate
 * stages of docs/14-import-export.md §14.2, run together since validation
 * needs the full parsed row set to detect in-batch duplicates).
 */
class ParseImportFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * docs/24-queue-management.md §24.1 — `imports` queue.
     *
     * Set in the constructor rather than as a typed property — `Queueable`
     * already declares an untyped `$queue`, and redeclaring it with a type
     * is a fatal trait-composition conflict.
     */
    public function __construct(private readonly int $importJobId)
    {
        $this->queue = 'imports';
    }

    public function handle(
        ImportJobService $jobs,
        CsvImportService $csv,
        ExcelImportService $excel,
        ImportValidationService $validation,
    ): void {
        $job = ImportJob::query()->findOrFail($this->importJobId);
        $job->update(['status' => ImportJobStatus::Validating->value, 'started_at' => now()]);

        try {
            $path = $jobs->absolutePath($job);
            $mapping = $job->column_mapping ?? [];

            $rows = match ($job->source_type) {
                ImportSourceType::Csv->value => $csv->parse($path),
                ImportSourceType::Excel->value => $excel->parse($path),
                default => throw new \RuntimeException("Type de source non pris en charge pour l'analyse : {$job->source_type}"),
            };

            $validation->validateAndStage($job, $rows, $mapping);

            $job->update(['status' => ImportJobStatus::Processing->value]);
        } catch (Throwable $e) {
            $job->errors()->create([
                'error_code' => 'parse_failed',
                'message' => $e->getMessage(),
            ]);
            $job->update(['status' => ImportJobStatus::Failed->value]);

            throw $e;
        }
    }
}
