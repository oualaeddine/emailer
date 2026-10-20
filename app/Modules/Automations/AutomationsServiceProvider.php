<?php

namespace App\Modules\Automations;

use App\Modules\Automations\Listeners\StartRunsOnAttributeChange;
use App\Modules\Automations\Listeners\StartRunsOnCampaignEngagement;
use App\Modules\Automations\Listeners\StartRunsOnFormSubmission;
use App\Modules\Automations\Listeners\StartRunsOnListAddition;
use App\Modules\Automations\Models\Automation;
use App\Modules\Automations\Policies\AutomationPolicy;
use App\Modules\Recipients\Attributes\Events\ContactAttributesUpdated;
use App\Modules\Recipients\Lists\Events\RecipientsAddedToList;
use App\Modules\Tracking\Events\MessageEventRecorded;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * docs/43 §43.4.19 / docs/45 §45.7.5 — WP-91 automations: policy + one
 * listener per trigger (the date-anniversary trigger is scanned by the
 * scheduler tick, `AdvanceAutomationRunsJob`). WP-90's form events are
 * registered by class name (string): registering never autoloads the
 * class, so this works whether or not the Forms module is present.
 */
class AutomationsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(Automation::class, AutomationPolicy::class);

        Event::listen(RecipientsAddedToList::class, StartRunsOnListAddition::class);
        Event::listen(MessageEventRecorded::class, StartRunsOnCampaignEngagement::class);
        Event::listen(ContactAttributesUpdated::class, StartRunsOnAttributeChange::class);
        Event::listen(StartRunsOnFormSubmission::FORM_SUBMITTED, [StartRunsOnFormSubmission::class, 'handle']);
        Event::listen(StartRunsOnFormSubmission::OPT_IN_CONFIRMED, [StartRunsOnFormSubmission::class, 'handleOptInConfirmed']);
    }
}
