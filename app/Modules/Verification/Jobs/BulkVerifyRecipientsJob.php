<?php

namespace App\Modules\Verification\Jobs;

use App\Modules\Verification\Services\VerificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * docs/22-email-verification.md §22.7 — bulk verification (the ad hoc
 * "Verify selected" action from the Recipients list; the import-time
 * "opt-in checkbox" workflow is a separate hook point inside
 * `App\Modules\Importing`, out of this work package's file scope — see
 * `App\Modules\Importing\Services\ImportCommitService::commit()`).
 *
 * docs/24-queue-management.md §24.1 — dispatched onto the `maintenance`
 * queue: a low-priority background bulk operation, and doc 24's queue
 * topology doesn't reserve a dedicated `verification` queue.
 *
 * Mirrors `App\Modules\Importing\Jobs\ParseImportFileJob`'s job-per-batch
 * shape: one queued job processes the whole list rather than one job per
 * recipient.
 */
class BulkVerifyRecipientsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Set in the constructor rather than as a typed property — `Queueable`
     * already declares an untyped `$queue`, and redeclaring it with a type
     * is a fatal trait-composition conflict.
     *
     * @param  list<string>  $recipientUuids
     */
    public function __construct(private readonly array $recipientUuids)
    {
        $this->queue = 'maintenance';
    }

    public function handle(VerificationService $verification): void
    {
        $verification->verifyBulk($this->recipientUuids);
    }
}
