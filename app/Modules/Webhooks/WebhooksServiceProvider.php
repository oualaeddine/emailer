<?php

namespace App\Modules\Webhooks;

use App\Modules\Recipients\Attributes\Events\ContactAttributesUpdated;
use App\Modules\Recipients\Events\RecipientDeleted;
use App\Modules\Recipients\Lists\Events\RecipientsAddedToList;
use App\Modules\Suppression\Listeners\RecipientBlocklistChanged;
use App\Modules\Suppression\Preferences\Events\ContactPreferencesUpdated;
use App\Modules\Tracking\Events\MessageEventRecorded;
use App\Modules\Webhooks\Listeners\WebhookEventSubscriber;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/** docs/45 §45.9 — WP-101: domain events -> outgoing webhooks. */
class WebhooksServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(MessageEventRecorded::class, [WebhookEventSubscriber::class, 'onMessageEvent']);
        Event::listen(RecipientsAddedToList::class, [WebhookEventSubscriber::class, 'onListAddition']);
        Event::listen(ContactAttributesUpdated::class, [WebhookEventSubscriber::class, 'onAttributesUpdated']);
        Event::listen(RecipientBlocklistChanged::class, [WebhookEventSubscriber::class, 'onBlocklistChanged']);
        Event::listen(RecipientDeleted::class, [WebhookEventSubscriber::class, 'onRecipientDeleted']);
        Event::listen(ContactPreferencesUpdated::class, [WebhookEventSubscriber::class, 'onPreferencesUpdated']);
    }
}
