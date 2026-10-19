<?php

namespace App\Modules\Suppression\Listeners;

use App\Modules\Suppression\Models\SuppressionEntry;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class SuppressionListenerServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen('eloquent.created: '.SuppressionEntry::class, [SyncRecipientBlocklist::class, 'handleCreated']);
        Event::listen('eloquent.deleted: '.SuppressionEntry::class, [SyncRecipientBlocklist::class, 'handleDeleted']);
    }
}
