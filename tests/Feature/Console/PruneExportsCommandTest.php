<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PruneExportsCommandTest extends TestCase
{
    public function test_prunes_only_old_exports(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('exports/old.csv', 'x');
        Storage::disk('local')->put('exports/new.csv', 'x');
        touch(Storage::disk('local')->path('exports/old.csv'), now()->subDays(3)->getTimestamp());

        $this->artisan('exports:prune')->assertSuccessful();

        Storage::disk('local')->assertMissing('exports/old.csv');
        Storage::disk('local')->assertExists('exports/new.csv');
    }
}
