<?php

namespace App\Modules\Importing\Jobs;

use App\Modules\Importing\Models\ImportJob;
use App\Modules\Importing\Services\ImportCommitService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * docs/30-background-jobs.md — `CommitImportJob`, keyed unique by
 * `import_job_id` (docs/30-background-jobs.md §30.2) so a duplicate
 * commit request can never double-import the same job.
 */
class CommitImportJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * docs/24-queue-management.md §24.1 — `imports` queue.
     *
     * Set in the constructor rather than as a typed property — `Queueable`
     * already declares an untyped `$queue`, and redeclaring it with a type
     * is a fatal trait-composition conflict.
     *
     * @param  list<int>  $excludedRowIds
     */
    public function __construct(private readonly int $importJobId, private readonly array $excludedRowIds = [])
    {
        $this->queue = 'imports';
    }

    public function uniqueId(): string
    {
        return (string) $this->importJobId;
    }

    public function handle(ImportCommitService $commit): void
    {
        $job = ImportJob::query()->findOrFail($this->importJobId);
        $commit->commit($job, $this->excludedRowIds);
    }
}
