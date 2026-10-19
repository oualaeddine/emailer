<?php

namespace App\Modules\Importing\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * docs/45-brevo-api-contracts.md §45.9 — fired when an import job finishes.
 */
class ImportCompleted implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /**
     * @param  array<string, int>  $summary
     */
    public function __construct(public readonly int $importJobId, public readonly array $summary)
    {
    }
}
