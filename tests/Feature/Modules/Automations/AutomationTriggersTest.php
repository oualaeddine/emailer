<?php

namespace Tests\Feature\Modules\Automations;

use App\Modules\Automations\Events\AutomationRunStarted;
use App\Modules\Automations\Jobs\AdvanceAutomationRunsJob;
use App\Modules\Automations\Models\Automation;
use App\Modules\Automations\Models\AutomationRun;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\DeliveryEngine\Jobs\SendEmailJob;
use App\Modules\Forms\Events\ContactOptInConfirmed;
use App\Modules\Forms\Events\FormSubmitted;
use App\Modules\Recipients\Attributes\Contracts\ContactAttributeService;
use App\Modules\Recipients\Attributes\Events\ContactAttributesUpdated;
use App\Modules\Recipients\Lists\Contracts\ListMembershipService;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Recipients\Models\RecipientList;
use App\Modules\Tracking\Models\Message;
use App\Modules\Tracking\Services\TrackingEventRecorder;
use Database\Factories\ContactAttributeFactory;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Feature\Modules\Automations\Concerns\BuildsAutomationFixtures;
use Tests\TestCase;

/**
 * docs/43 §43.4.19 / docs/45 §45.7.5 — every trigger starts a run when its
 * real domain event fires; trigger filter and re-entry rules.
 */
class AutomationTriggersTest extends TestCase
{
    use BuildsAutomationFixtures, RefreshDatabase {
        beginDatabaseTransaction as refreshDatabaseBeginTransaction;
    }

    /**
     * Until WP-90's `forms` migration is in this checkout, a minimal `forms`
     * table is created here, before the test transaction opens (DDL would
     * commit it implicitly on MySQL).
     */
    public function beginDatabaseTransaction()
    {
        if (! Schema::hasTable('forms')) {
            Schema::create('forms', function ($table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('name')->nullable();
                $table->timestamps();
            });
        }

        $this->refreshDatabaseBeginTransaction();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Bus::fake([SendEmailJob::class]);
    }

    public function test_contact_added_to_list_starts_a_run_and_sends_the_first_email(): void
    {
        $list = $this->list();
        $other = $this->list();
        $automation = $this->activeAutomation('list_added', ['list_ids' => [$list->uuid]], [$this->sendEmailStep($this->template())]);
        $contact = $this->contact();

        app(ListMembershipService::class)->add($other, [$contact->id], 'manual');
        $this->assertSame(0, AutomationRun::query()->count());

        app(ListMembershipService::class)->add($list, [$contact->id], 'manual');

        $run = AutomationRun::query()->sole();
        $this->assertSame($automation->id, $run->automation_id);
        $this->assertSame($contact->id, $run->recipient_id);
        $this->assertSame('completed', $run->status);
        $this->assertSame('list_added', $run->context['trigger']);

        $message = Message::query()->sole();
        $this->assertNull($message->campaign_id);
        $this->assertFalse((bool) $message->is_test);
        $this->assertSame($this->stepAt($automation, 0)->id, (int) $message->getAttribute('automation_step_id'));
        Bus::assertDispatched(SendEmailJob::class, fn (SendEmailJob $job) => $job->messageId === $message->id);
    }

    public function test_run_started_event_is_fired(): void
    {
        Event::fake([AutomationRunStarted::class]);
        $list = $this->list();
        $this->activeAutomation('list_added', ['list_ids' => [$list->uuid]], [$this->step('end')]);
        $contact = $this->contact();

        app(ListMembershipService::class)->add($list, [$contact->id], 'manual');

        Event::assertDispatched(AutomationRunStarted::class, fn (AutomationRunStarted $e) => $e->recipientId === $contact->id);
    }

    public function test_draft_or_paused_automation_does_not_start(): void
    {
        $list = $this->list();
        $paused = $this->activeAutomation('list_added', ['list_ids' => [$list->uuid]], [$this->step('end')]);
        $paused->update(['status' => 'paused']);

        app(ListMembershipService::class)->add($list, [$this->contact()->id], 'manual');

        $this->assertSame(0, AutomationRun::query()->count());
    }

    public function test_form_submission_starts_on_subscribed_and_opt_in_confirmation_only(): void
    {
        $formId = $this->form();
        $formUuid = DB::table('forms')->where('id', $formId)->value('uuid');
        $this->activeAutomation('form_submitted', ['form_ids' => [$formUuid]], [$this->step('end')], ['allow_reentry' => true]);
        $a = $this->contact();
        $b = $this->contact();

        FormSubmitted::dispatch($formId, 1, $a->id, 'pending_confirmation');
        FormSubmitted::dispatch($formId, 2, null, 'subscribed');
        $this->assertSame(0, AutomationRun::query()->count());

        FormSubmitted::dispatch($formId, 3, $a->id, 'subscribed');
        $this->assertSame(1, AutomationRun::query()->where('recipient_id', $a->id)->count());

        ContactOptInConfirmed::dispatch($b->id, $formId);
        $this->assertSame(1, AutomationRun::query()->where('recipient_id', $b->id)->count());
        $this->assertSame('form_submitted', AutomationRun::query()->where('recipient_id', $b->id)->sole()->context['trigger']);
    }

    public function test_campaign_open_starts_runs_for_matching_campaigns(): void
    {
        $campaign = Campaign::factory()->create();
        $otherCampaign = Campaign::factory()->create();
        $specific = $this->activeAutomation('campaign_opened', ['campaign_ids' => [$campaign->uuid]], [$this->step('end')]);
        $any = $this->activeAutomation('campaign_opened', ['campaign_ids' => null], [$this->step('end')]);
        $contact = $this->contact();

        $recorder = app(TrackingEventRecorder::class);
        $recorder->recordOpen($this->campaignMessage($otherCampaign, $contact));

        $this->assertSame(0, AutomationRun::query()->where('automation_id', $specific->id)->count());
        $this->assertSame(1, AutomationRun::query()->where('automation_id', $any->id)->count());

        $recorder->recordOpen($this->campaignMessage($campaign, $contact));
        $this->assertSame(1, AutomationRun::query()->where('automation_id', $specific->id)->count());
    }

    public function test_campaign_click_matches_link_url_and_ignores_test_sends(): void
    {
        $campaign = Campaign::factory()->create();
        $automation = $this->activeAutomation('campaign_clicked', ['campaign_ids' => [$campaign->uuid], 'link_url' => 'https://exemple.dz/promo*'], [$this->step('end')], ['allow_reentry' => true]);
        $contact = $this->contact();
        $recorder = app(TrackingEventRecorder::class);

        $recorder->recordClick($this->campaignMessage($campaign, $contact), 'https://exemple.dz/autre');
        $test = $this->campaignMessage($campaign, $contact);
        $test->forceFill(['is_test' => true])->save();
        $recorder->recordClick($test, 'https://exemple.dz/promo/ete');
        $recorder->recordOpen($this->campaignMessage($campaign, $contact));
        $this->assertSame(0, AutomationRun::query()->count());

        $recorder->recordClick($this->campaignMessage($campaign, $contact), 'https://exemple.dz/promo/ete');
        $this->assertSame(1, AutomationRun::query()->where('automation_id', $automation->id)->count());
    }

    public function test_attribute_change_starts_run_for_matching_value(): void
    {
        ContactAttributeFactory::new()->create(['name' => 'STATUT', 'type' => 'text']);
        $automation = $this->activeAutomation('attribute_changed', ['attribute' => 'STATUT', 'to_value' => 'client'], [$this->step('end')]);
        $contact = $this->contact();
        $attributes = app(ContactAttributeService::class);

        $attributes->apply($contact, ['STATUT' => 'prospect'], 'manual');
        $this->assertSame(0, AutomationRun::query()->count());

        $attributes->apply($contact->refresh(), ['STATUT' => 'Client'], 'manual');
        $this->assertSame(1, AutomationRun::query()->where('automation_id', $automation->id)->count());
    }

    public function test_attribute_change_with_null_to_value_matches_any_change(): void
    {
        $automation = $this->activeAutomation('attribute_changed', ['attribute' => 'VILLE', 'to_value' => null], [$this->step('end')]);
        $contact = $this->contact();

        ContactAttributesUpdated::dispatch($contact->id, ['PRENOM' => ['old' => null, 'new' => 'Amina']], 'manual');
        $this->assertSame(0, AutomationRun::query()->count());

        ContactAttributesUpdated::dispatch($contact->id, ['VILLE' => ['old' => null, 'new' => 'oran']], 'manual');
        $this->assertSame(1, AutomationRun::query()->where('automation_id', $automation->id)->count());
    }

    public function test_date_anniversary_scan_runs_once_a_day_after_the_configured_time(): void
    {
        ContactAttributeFactory::new()->create(['name' => 'DATE_NAISSANCE', 'type' => 'date']);
        $automation = $this->activeAutomation('date_anniversary', ['attribute' => 'DATE_NAISSANCE', 'offset_days' => 0, 'time' => '09:00'], [$this->step('end')], ['allow_reentry' => true]);
        $birthday = $this->contact(['custom_fields' => ['DATE_NAISSANCE' => '1990-03-15']]);
        $this->contact(['custom_fields' => ['DATE_NAISSANCE' => '1990-03-16']]);
        $this->contact();

        // 08:30 in Algiers (UTC+1): too early.
        $this->travelTo(Carbon::parse('2027-03-15 07:30:00', 'UTC'));
        dispatch(new AdvanceAutomationRunsJob);
        $this->assertSame(0, AutomationRun::query()->count());

        $this->travelTo(Carbon::parse('2027-03-15 08:05:00', 'UTC'));
        dispatch(new AdvanceAutomationRunsJob);
        $this->assertSame(1, AutomationRun::query()->where('automation_id', $automation->id)->count());
        $this->assertSame($birthday->id, AutomationRun::query()->value('recipient_id'));

        // Later ticks the same day do not re-scan.
        $this->travel(2)->hours();
        dispatch(new AdvanceAutomationRunsJob);
        $this->assertSame(1, AutomationRun::query()->count());
    }

    public function test_date_anniversary_offset_days_starts_before_the_date(): void
    {
        ContactAttributeFactory::new()->create(['name' => 'DATE_CONTRAT', 'type' => 'date']);
        $this->activeAutomation('date_anniversary', ['attribute' => 'DATE_CONTRAT', 'offset_days' => -7, 'time' => '00:00'], [$this->step('end')]);
        $contact = $this->contact(['custom_fields' => ['DATE_CONTRAT' => '2020-06-20']]);

        $this->travelTo(Carbon::parse('2027-06-13 12:00:00', 'UTC'));
        dispatch(new AdvanceAutomationRunsJob);

        $this->assertSame($contact->id, AutomationRun::query()->sole()->recipient_id);
    }

    public function test_trigger_filter_only_lets_matching_contacts_enter(): void
    {
        $list = $this->list();
        $this->activeAutomation('list_added', ['list_ids' => [$list->uuid], 'filter' => [
            'version' => 2, 'type' => 'group', 'operator' => 'and', 'children' => [
                ['type' => 'condition', 'field' => 'attribute.EMAIL', 'operator' => 'ends_with', 'value' => '@client.dz'],
            ],
        ]], [$this->step('end')]);
        $in = $this->contact(['email' => 'amina@client.dz']);
        $out = $this->contact(['email' => 'karim@autre.dz']);

        app(ListMembershipService::class)->add($list, [$in->id, $out->id], 'manual');

        $this->assertSame([$in->id], AutomationRun::query()->pluck('recipient_id')->all());
    }

    public function test_reentry_never(): void
    {
        [$list, $automation, $contact] = $this->reentryFixture(['allow_reentry' => false]);

        $this->readd($list, $contact);
        $this->travel(30)->days();
        $this->readd($list, $contact);

        $this->assertSame(1, AutomationRun::query()->where('automation_id', $automation->id)->count());
    }

    public function test_reentry_after_n_days(): void
    {
        [$list, $automation, $contact] = $this->reentryFixture(['allow_reentry' => true, 'reentry_after_days' => 7]);

        $this->travel(3)->days();
        $this->readd($list, $contact);
        $this->assertSame(1, AutomationRun::query()->where('automation_id', $automation->id)->count());

        $this->travel(5)->days();
        $this->readd($list, $contact);
        $this->assertSame(2, AutomationRun::query()->where('automation_id', $automation->id)->count());
    }

    public function test_reentry_always_but_never_two_runs_in_progress(): void
    {
        $list = $this->list();
        $automation = $this->activeAutomation('list_added', ['list_ids' => [$list->uuid]], [$this->waitStep(1)], ['allow_reentry' => true]);
        $contact = $this->contact();
        app(ListMembershipService::class)->add($list, [$contact->id], 'manual');

        $this->readd($list, $contact);
        $this->assertSame(1, AutomationRun::query()->count(), 'a run is still waiting');

        $this->travel(1)->days();
        $this->travel(1)->minutes();
        dispatch(new AdvanceAutomationRunsJob);
        $this->assertSame('completed', AutomationRun::query()->sole()->status);

        $this->readd($list, $contact);
        $this->assertSame(2, AutomationRun::query()->where('automation_id', $automation->id)->count());
    }

    /** @return array{0: RecipientList, 1: Automation, 2: Recipient} */
    private function reentryFixture(array $attributes): array
    {
        $list = $this->list();
        $automation = $this->activeAutomation('list_added', ['list_ids' => [$list->uuid]], [$this->step('end')], $attributes);
        $contact = $this->contact();
        app(ListMembershipService::class)->add($list, [$contact->id], 'manual');
        $this->assertSame(1, AutomationRun::query()->count());

        return [$list, $automation, $contact];
    }

    private function readd($list, $contact): void
    {
        $membership = app(ListMembershipService::class);
        $membership->remove($list, [$contact->id]);
        $membership->add($list, [$contact->id], 'manual');
    }

    private function campaignMessage(Campaign $campaign, $contact): Message
    {
        return Message::factory()->create([
            'campaign_id' => $campaign->id,
            'recipient_id' => $contact->id,
            'status' => 'delivered',
        ]);
    }

    /** A `forms` row (only the columns this module reads, plus required ones when WP-90's table exists). */
    private function form(): int
    {
        $row = ['uuid' => (string) Str::uuid(), 'created_at' => now(), 'updated_at' => now()];
        foreach (['name' => 'Inscription', 'status' => 'active'] as $column => $value) {
            if (Schema::hasColumn('forms', $column)) {
                $row[$column] = $value;
            }
        }
        foreach (['fields', 'list_ids', 'gdpr', 'design', 'settings'] as $json) {
            if (Schema::hasColumn('forms', $json)) {
                $row[$json] = '[]';
            }
        }

        return (int) DB::table('forms')->insertGetId($row);
    }
}
