<?php

namespace Tests\Feature\Modules\Automations;

use App\Modules\Automations\Events\AutomationEmailSent;
use App\Modules\Automations\Events\AutomationRunFinished;
use App\Modules\Automations\Jobs\AdvanceAutomationRunsJob;
use App\Modules\Automations\Models\Automation;
use App\Modules\Automations\Models\AutomationRun;
use App\Modules\Automations\Models\AutomationStepLog;
use App\Modules\Automations\Services\AutomationEmailSender;
use App\Modules\Automations\Services\TriggerDispatcher;
use App\Modules\DeliveryEngine\Jobs\SendEmailJob;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Suppression\Models\SuppressionEntry;
use App\Modules\Tracking\Models\Message;
use Database\Factories\ContactAttributeFactory;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Feature\Modules\Automations\Concerns\BuildsAutomationFixtures;
use Tests\TestCase;

/**
 * docs/43 §43.4.19 — step execution: every step type, waits resumed by the
 * tick after time travel, condition branches joining the next common step,
 * pause freezing runs, failures.
 */
class AutomationRunnerTest extends TestCase
{
    use BuildsAutomationFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Bus::fake([SendEmailJob::class]);
    }

    private function enter(Automation $automation, Recipient $contact): ?AutomationRun
    {
        return app(TriggerDispatcher::class)->start($automation, $contact->id, ['trigger' => 'test']);
    }

    private function tick(): void
    {
        dispatch(new AdvanceAutomationRunsJob);
    }

    public function test_welcome_series_with_waits_advances_on_the_tick(): void
    {
        $first = $this->template('J+0');
        $second = $this->template('J+2');
        $automation = $this->activeAutomation('list_added', ['list_ids' => []], [
            $this->sendEmailStep($first),
            $this->waitStep(2, 'days'),
            $this->sendEmailStep($second),
            $this->waitStep(3, 'hours'),
            $this->step('end'),
        ]);
        $contact = $this->contact();

        $run = $this->enter($automation, $contact)->refresh();
        $this->assertSame('waiting', $run->status);
        $this->assertSame($this->stepAt($automation, 1)->id, $run->current_step_id);
        $this->assertTrue($run->next_run_at->equalTo(now()->addDays(2)->startOfSecond()) || $run->next_run_at->diffInSeconds(now()->addDays(2)) < 2);
        $this->assertSame(1, Message::query()->count());

        $this->travel(1)->days();
        $this->tick();
        $this->assertSame('waiting', $run->refresh()->status);
        $this->assertSame(1, Message::query()->count());

        $this->travel(1)->days();
        $this->travel(1)->minutes();
        $this->tick();
        $run->refresh();
        $this->assertSame('waiting', $run->status);
        $this->assertSame($this->stepAt($automation, 3)->id, $run->current_step_id);
        $this->assertSame(2, Message::query()->count());
        $this->assertSame($this->stepAt($automation, 2)->id, (int) Message::query()->latest('id')->first()->getAttribute('automation_step_id'));

        $this->travel(3)->hours();
        $this->tick();
        $run->refresh();
        $this->assertSame('completed', $run->status);
        $this->assertNotNull($run->finished_at);
        $this->assertNull($run->next_run_at);
        $this->assertSame(5, AutomationStepLog::query()->where('automation_run_id', $run->id)->count());
        Bus::assertDispatched(SendEmailJob::class, 2);
    }

    public function test_wait_in_minutes(): void
    {
        $automation = $this->activeAutomation('list_added', ['list_ids' => []], [$this->waitStep(30, 'minutes'), $this->sendEmailStep($this->template())]);
        $run = $this->enter($automation, $this->contact());

        $this->travel(29)->minutes();
        $this->tick();
        $this->assertSame('waiting', $run->refresh()->status);

        $this->travel(1)->minutes();
        $this->tick();
        $this->assertSame('completed', $run->refresh()->status);
        $this->assertSame(1, Message::query()->count());
    }

    public function test_condition_branches_then_join_the_next_common_step(): void
    {
        $vip = $this->template('VIP');
        $standard = $this->template('Standard');
        $common = $this->template('Commun');
        $rules = ['version' => 2, 'type' => 'group', 'operator' => 'and', 'children' => [
            ['type' => 'condition', 'field' => 'attribute.EMAIL', 'operator' => 'ends_with', 'value' => '@vip.dz'],
        ]];
        $automation = $this->activeAutomation('list_added', ['list_ids' => []], [
            $this->step('condition', ['rules' => $rules], [$this->sendEmailStep($vip)], [$this->sendEmailStep($standard)]),
            $this->sendEmailStep($common),
        ]);
        $condition = $this->stepAt($automation, 0);

        $yesRun = $this->enter($automation, $this->contact(['email' => 'amina@vip.dz']))->refresh();
        $noRun = $this->enter($automation, $this->contact(['email' => 'karim@exemple.dz']))->refresh();

        $this->assertSame('completed', $yesRun->status);
        $this->assertSame('completed', $noRun->status);
        $this->assertSame('yes', AutomationStepLog::query()->where('automation_run_id', $yesRun->id)->where('automation_step_id', $condition->id)->value('outcome'));
        $this->assertSame('no', AutomationStepLog::query()->where('automation_run_id', $noRun->id)->where('automation_step_id', $condition->id)->value('outcome'));

        $yesSteps = Message::query()->where('recipient_id', $yesRun->recipient_id)->orderBy('id')->pluck('automation_step_id')->map(fn ($id) => (int) $id)->all();
        $noSteps = Message::query()->where('recipient_id', $noRun->recipient_id)->orderBy('id')->pluck('automation_step_id')->map(fn ($id) => (int) $id)->all();
        $this->assertSame([$this->stepAt($automation, 0, $condition, 'yes')->id, $this->stepAt($automation, 1)->id], $yesSteps);
        $this->assertSame([$this->stepAt($automation, 0, $condition, 'no')->id, $this->stepAt($automation, 1)->id], $noSteps);
    }

    public function test_empty_branch_continues_with_the_next_step_and_end_stops_a_branch(): void
    {
        $rules = ['version' => 2, 'type' => 'group', 'operator' => 'and', 'children' => [
            ['type' => 'condition', 'field' => 'attribute.EMAIL', 'operator' => 'ends_with', 'value' => '@stop.dz'],
        ]];
        $automation = $this->activeAutomation('list_added', ['list_ids' => []], [
            $this->step('condition', ['rules' => $rules], [$this->step('end')], []),
            $this->sendEmailStep($this->template()),
        ]);

        $stopped = $this->enter($automation, $this->contact(['email' => 'a@stop.dz']));
        $continued = $this->enter($automation, $this->contact(['email' => 'b@go.dz']));

        $this->assertSame('completed', $stopped->refresh()->status);
        $this->assertSame(0, Message::query()->where('recipient_id', $stopped->recipient_id)->count());
        $this->assertSame(1, Message::query()->where('recipient_id', $continued->recipient_id)->count());
    }

    public function test_add_and_remove_list_steps(): void
    {
        $target = $this->list();
        $source = $this->list();
        $contact = $this->contact();
        DB::table('recipient_list_recipient')->insert(['recipient_list_id' => $source->id, 'recipient_id' => $contact->id, 'added_at' => now()]);
        $automation = $this->activeAutomation('list_added', ['list_ids' => []], [
            $this->step('add_to_list', ['list_id' => $target->uuid]),
            $this->step('remove_from_list', ['list_id' => $source->uuid]),
        ]);

        $run = $this->enter($automation, $contact);

        $this->assertSame('completed', $run->refresh()->status);
        $this->assertDatabaseHas('recipient_list_recipient', ['recipient_list_id' => $target->id, 'recipient_id' => $contact->id, 'source' => 'automation']);
        $this->assertDatabaseMissing('recipient_list_recipient', ['recipient_list_id' => $source->id, 'recipient_id' => $contact->id]);
    }

    public function test_update_attribute_step(): void
    {
        ContactAttributeFactory::new()->create(['name' => 'STATUT', 'type' => 'text']);
        $automation = $this->activeAutomation('list_added', ['list_ids' => []], [
            $this->step('update_attribute', ['attribute' => 'statut', 'value' => 'client']),
        ]);
        $contact = $this->contact();

        $this->enter($automation, $contact);

        $this->assertSame('client', $contact->refresh()->custom_fields['STATUT'] ?? null);
    }

    public function test_end_step_completes_the_run(): void
    {
        $automation = $this->activeAutomation('list_added', ['list_ids' => []], [$this->step('end')]);
        Event::fake([AutomationRunFinished::class]);

        $run = $this->enter($automation, $this->contact());

        $this->assertSame('completed', $run->refresh()->status);
        Event::assertDispatched(AutomationRunFinished::class, fn ($e) => $e->runId === $run->id && $e->status === 'completed');
    }

    public function test_send_email_step_message_and_events(): void
    {
        Event::fake([AutomationEmailSent::class]);
        $template = $this->template();
        $automation = $this->activeAutomation('list_added', ['list_ids' => []], [
            $this->sendEmailStep($template, ['config' => ['template_id' => $template->uuid, 'subject' => 'Objet personnalisé', 'sender_id' => null, 'preview_text' => 'Aperçu']]),
        ]);
        $contact = $this->contact(['first_name' => 'Amina']);

        $run = $this->enter($automation, $contact);

        $message = Message::query()->sole();
        $this->assertSame('Objet personnalisé', $message->subject);
        $this->assertStringContainsString('Bonjour Amina', $message->html_body);
        $this->assertStringContainsString('Aperçu', $message->html_body);
        $this->assertSame('queued', $message->status);
        $this->assertSame($message->id, AutomationStepLog::query()->where('automation_run_id', $run->id)->value('message_id'));
        Event::assertDispatched(AutomationEmailSent::class, fn ($e) => $e->messageId === $message->id && $e->runId === $run->id);
    }

    public function test_send_email_uses_the_transactional_contract_when_bound(): void
    {
        $template = $this->template();
        $automation = $this->activeAutomation('list_added', ['list_ids' => []], [$this->sendEmailStep($template)]);
        $contact = $this->contact();
        $calls = [];

        app()->bind(AutomationEmailSender::TRANSACTIONAL_SENDER, function () use (&$calls) {
            return new class($calls)
            {
                public function __construct(private array &$calls) {}

                public function send(array $input, array $context = []): array
                {
                    $this->calls[] = [$input, $context];

                    return [Message::factory()->create(['recipient_id' => Recipient::query()->where('email', $input['to'][0]['email'])->value('id')])];
                }
            };
        });

        $this->enter($automation, $contact);

        $this->assertCount(1, $calls);
        [$input, $context] = $calls[0];
        $this->assertSame($template->uuid, $input['template_id']);
        $this->assertSame($contact->email, $input['to'][0]['email']);
        $this->assertSame(['source' => 'automation', 'automation_step_id' => $this->stepAt($automation, 0)->id, 'actor' => null], $context);
        Bus::assertNotDispatched(SendEmailJob::class);
    }

    public function test_suppressed_contact_is_skipped_but_continues(): void
    {
        $automation = $this->activeAutomation('list_added', ['list_ids' => []], [$this->sendEmailStep($this->template()), $this->step('end')]);
        $contact = $this->contact();
        SuppressionEntry::factory()->create(['email' => $contact->email]);

        $run = $this->enter($automation, $contact);

        $this->assertSame('completed', $run->refresh()->status);
        $this->assertSame(0, Message::query()->count());
        $this->assertSame('skipped', AutomationStepLog::query()->where('automation_step_id', $this->stepAt($automation, 0)->id)->value('outcome'));
    }

    public function test_missing_template_fails_the_run(): void
    {
        $template = $this->template();
        $automation = $this->activeAutomation('list_added', ['list_ids' => []], [$this->sendEmailStep($template)]);
        $template->delete();

        $run = $this->enter($automation, $this->contact());

        $run->refresh();
        $this->assertSame('failed', $run->status);
        $this->assertSame('Modèle d’e-mail introuvable.', $run->error);
        $this->assertSame('failed', AutomationStepLog::query()->where('automation_run_id', $run->id)->value('outcome'));
    }

    public function test_pause_freezes_runs_and_activate_resumes_them(): void
    {
        $automation = $this->activeAutomation('list_added', ['list_ids' => []], [$this->waitStep(1, 'hours'), $this->sendEmailStep($this->template())]);
        $run = $this->enter($automation, $this->contact());
        $automation->update(['status' => 'paused']);

        $this->travel(2)->hours();
        $this->tick();
        $this->assertSame('waiting', $run->refresh()->status);
        $this->assertSame(0, Message::query()->count());

        $this->assertNull($this->enter($automation, $this->contact()), 'paused automations accept no new run');

        $automation->update(['status' => 'active']);
        $this->tick();
        $this->assertSame('completed', $run->refresh()->status);
        $this->assertSame(1, Message::query()->count());
    }

    public function test_deleted_contact_exits_on_next_tick(): void
    {
        $automation = $this->activeAutomation('list_added', ['list_ids' => []], [$this->waitStep(1, 'hours'), $this->step('end')]);
        $contact = $this->contact();
        $run = $this->enter($automation, $contact);
        $contact->delete();

        $this->travel(2)->hours();
        $this->tick();

        $this->assertSame('exited', $run->refresh()->status);
    }
}
