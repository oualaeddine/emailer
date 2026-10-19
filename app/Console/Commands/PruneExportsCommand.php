<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneExportsCommand extends Command
{
    protected $signature = 'exports:prune {--days=2 : Delete exports older than this many days}';

    protected $description = 'Delete generated export files (storage/app/exports) older than 2 days';

    public function handle(): int
    {
        $disk = Storage::disk('local');
        $cutoff = now()->subDays((int) $this->option('days'))->getTimestamp();
        $deleted = 0;

        foreach ($disk->files('exports') as $file) {
            if ($disk->lastModified($file) < $cutoff) {
                $disk->delete($file);
                $deleted++;
            }
        }

        $this->info("Pruned {$deleted} export file(s).");

        return self::SUCCESS;
    }
}
