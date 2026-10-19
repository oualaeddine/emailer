<?php

namespace App\Console\Commands;

use App\Modules\Recipients\Jobs\RecomputeRecipientCountersJob;
use Illuminate\Console\Command;

class RecomputeRecipientCountersCommand extends Command
{
    protected $signature = 'recipients:recompute-counters {--recipient= : Only this recipient id}';

    protected $description = 'Recompute cached recipient engagement counters from messages';

    public function handle(): int
    {
        $id = $this->option('recipient');
        RecomputeRecipientCountersJob::dispatchSync($id !== null && $id !== '' ? (int) $id : null);
        $this->info('Recipient counters recomputed.');

        return self::SUCCESS;
    }
}
