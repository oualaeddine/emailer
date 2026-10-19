<?php

namespace App\Modules\Recipients\Lists;

use App\Modules\Recipients\Events\RecipientDeleted;
use App\Modules\Recipients\Lists\Listeners\RecountListsForRecipient;
use App\Modules\Suppression\Listeners\RecipientBlocklistChanged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class ListsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(RecipientDeleted::class, RecountListsForRecipient::class);
        Event::listen(RecipientBlocklistChanged::class, RecountListsForRecipient::class);
    }
}
