<?php

namespace Tests\Feature\Modules\Automations;

use App\Domain\Enums\RoleName;
use App\Modules\Automations\Jobs\AdvanceAutomationRunsJob;
use App\Modules\Automations\Models\Automation;
use App\Modules\Automations\Models\AutomationRun;
use App\Modules\Automations\Models\AutomationStep;
use App\Modules\Automations\Services\TriggerDispatcher;
use App\Modules\DeliveryEngine\Jobs\SendEmailJob;
use App\Modules\Identity\Models\User;
use App\Modules\Tracking\Models\Message;
use App\Modules\Tracking\Services\TrackingEventRecorder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Modules\Automations\Concerns\BuildsAutomationFixtures;
use Tests\TestCase;

/** docs/45 §45.7.5 — HTTP contract, permissions (§45.2) and state conflicts. */
class AutomationApiTest extends TestCase
{
    use BuildsAutomationFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Bus::fake([SendEmailJob::class]);
    }

    private function actingAsManager(): User
    {
        $user = $this->manager();
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_only_users_with_automations_manage_reach_the_endpoints(): void
    {
        $automation = Automation::factory()->create();

        foreach ([RoleName::MarketingOperator, RoleName::Viewer] as $role) {
            Sanctum::actingAs(User::factory()->withRole($role)->create());
            $this->getJson('/api/v1/automations')->assertForbidden();
            $this->getJson("/api/v1/automations/{$automation->uuid}")->assertForbidden();
            $this->postJson('/api/v1/automations', ['name' => 'X', 'trigger' => ['type' => 'list_added', 'config' => ['list_ids' => []]]])->assertForbidden();
            $this->putJson("/api/v1/automations/{$automation->uuid}/steps", ['steps' => []])->assertForbidden();
            $this->postJson("/api/v1/automations/{$automation->uuid}/activate")->assertForbidden();
            $this->deleteJson("/api/v1/automations/{$automation->uuid}")->assertForbidden();
            $this->getJson("/api/v1/automations/{$automation->uuid}/runs")->assertForbidden();
            $this->getJson("/api/v1/automations/{$automation->uuid}/activity")->assertForbidden();
        }

        foreach ([RoleName::Administrator, RoleName::MarketingManager] as $role) {
            Sanctum::actingAs(User::factory()->withRole($role)->create());
            $this->getJson('/api/v1/automations')->assertOk();
        }
    }

    public function test_create_show_list_and_update(): void
    {
        $user = $this->actingAsManager();
        $list = $this->list();
        $template = $this->template();

        $created = $this->postJson('/api/v1/automations', [
            'name' => 'Série de bienvenue',
            'description' => 'Trois e-mails',
            'trigger' => ['type' => 'list_added', 'config' => ['list_ids' => [$list->uuid]]],
            'allow_reentry' => true,
            'reentry_after_days' => 30,
            'steps' => [$this->sendEmailStep($template)],
        ])->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.trigger.type', 'list_added')
            ->assertJsonPath('data.trigger.config.list_ids', [$list->uuid])
            ->assertJsonPath('data.trigger.config.filter', null)
            ->assertJsonPath('data.allow_reentry', true)
            ->assertJsonPath('data.reentry_after_days', 30)
            ->assertJsonPath('data.stats', ['started' => 0, 'in_progress' => 0, 'completed' => 0, 'exited' => 0])
            ->assertJsonPath('data.created_by.id', $user->uuid)
            ->assertJsonPath('data.steps.0.type', 'send_email')
            ->assertJsonPath('data.steps.0.config.template_id', $template->uuid)
            ->assertJsonPath('data.steps.0.stats', ['entered' => 0, 'completed' => 0])
            ->json('data');

        $this->getJson("/api/v1/automations/{$created['id']}")->assertOk()->assertJsonPath('data.name', 'Série de bienvenue');

        Automation::factory()->create(['name' => 'Relance panier']);
        $this->getJson('/api/v1/automations?q=bienvenue')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonMissingPath('data.0.steps')
            ->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/automations?status=active')->assertOk()->assertJsonCount(0, 'data');

        $this->patchJson("/api/v1/automations/{$created['id']}", ['name' => 'Bienvenue v2', 'allow_reentry' => false])
            ->assertOk()
            ->assertJsonPath('data.name', 'Bienvenue v2')
            ->assertJsonPath('data.reentry_after_days', null);
    }

    public function test_validation_errors(): void
    {
        $this->actingAsManager();

        $this->postJson('/api/v1/automations', ['trigger' => ['type' => 'unknown']])
            ->assertUnprocessable()->assertJsonValidationErrors(['name', 'trigger.type']);

        $this->postJson('/api/v1/automations', [
            'name' => 'X', 'trigger' => ['type' => 'list_added', 'config' => ['list_ids' => ['00000000-0000-4000-8000-000000000000']]],
        ])->assertUnprocessable()->assertJsonValidationErrors(['trigger.config.list_ids.0']);

        $this->postJson('/api/v1/automations', [
            'name' => 'X', 'trigger' => ['type' => 'attribute_changed', 'config' => ['attribute' => 'INCONNU']],
        ])->assertUnprocessable()->assertJsonValidationErrors(['trigger.config.attribute']);

        $automation = Automation::factory()->create();
        $this->putJson("/api/v1/automations/{$automation->uuid}/steps", ['steps' => [
            $this->waitStep(400, 'days'),
            $this->step('end'),
            $this->step('add_to_list', ['list_id' => null], [$this->step('end')]),
        ]])->assertUnprocessable()->assertJsonValidationErrors(['steps.0.config.amount', 'steps.1.type', 'steps.2']);

        $deep = $this->step('condition', ['rules' => null], [$this->step('condition', [], [$this->step('condition', [], [$this->step('condition')])])]);
        $this->putJson("/api/v1/automations/{$automation->uuid}/steps", ['steps' => [$deep]])
            ->assertUnprocessable()->assertJsonValidationErrors(['steps.0.yes.0.yes.0.yes.0.type']);

        $this->putJson("/api/v1/automations/{$automation->uuid}/steps", ['steps' => array_fill(0, 51, $this->step('wait', ['amount' => 1, 'unit' => 'days']))])
            ->assertUnprocessable()->assertJsonValidationErrors(['steps']);
    }

    public function test_put_steps_replaces_the_tree_and_keeps_ids(): void
    {
        $this->actingAsManager();
        $template = $this->template();
        $automation = Automation::factory()->create();

        $tree = $this->putJson("/api/v1/automations/{$automation->uuid}/steps", ['steps' => [
            array_replace($this->sendEmailStep($template), ['position' => 0]),
            ['type' => 'condition', 'position' => 1, 'config' => ['rules' => ['version' => 2, 'type' => 'group', 'operator' => 'and', 'children' => []]],
                'yes' => [array_replace($this->waitStep(2), ['position' => 0])],
                'no' => [['type' => 'end', 'position' => 0, 'config' => [], 'yes' => [], 'no' => []]]],
        ]])->assertOk()
            ->assertJsonPath('data.steps.1.type', 'condition')
            ->assertJsonPath('data.steps.1.yes.0.type', 'wait')
            ->assertJsonPath('data.steps.1.no.0.type', 'end')
            ->json('data.steps');
        $this->assertSame(4, AutomationStep::query()->where('automation_id', $automation->id)->count());

        // Reorder: keep the e-mail step (by id) after a new wait; drop the condition.
        $this->putJson("/api/v1/automations/{$automation->uuid}/steps", ['steps' => [
            array_replace($this->waitStep(1), ['position' => 0]),
            array_replace($this->sendEmailStep($template), ['id' => $tree[0]['id'], 'position' => 1]),
        ]])->assertOk()
            ->assertJsonPath('data.steps.0.type', 'wait')
            ->assertJsonPath('data.steps.1.id', $tree[0]['id']);
        $this->assertSame(2, AutomationStep::query()->where('automation_id', $automation->id)->count());
    }

    public function test_activate_requires_a_valid_trigger_and_steps(): void
    {
        $this->actingAsManager();
        $list = $this->list();
        $automation = Automation::factory()->trigger('list_added', ['list_ids' => []])->create();

        $this->postJson("/api/v1/automations/{$automation->uuid}/activate")
            ->assertUnprocessable()->assertJsonValidationErrors(['steps', 'trigger.config.list_ids']);

        $automation->update(['trigger_config' => ['list_ids' => [$list->uuid]]]);
        $this->setSteps($automation, [$this->step('send_email', ['template_id' => null])]);
        $this->postJson("/api/v1/automations/{$automation->uuid}/activate")
            ->assertUnprocessable()->assertJsonValidationErrors(['steps', 'steps.0.config.template_id']);

        $this->setSteps($automation, [$this->sendEmailStep($this->template())]);
        $this->postJson("/api/v1/automations/{$automation->uuid}/activate")
            ->assertOk()->assertJsonPath('data.status', 'active');
        $this->assertNotNull($automation->refresh()->activated_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'automation.activated', 'auditable_id' => $automation->id]);
    }

    public function test_pause_activate_and_state_conflicts(): void
    {
        $this->actingAsManager();
        $list = $this->list();
        $automation = $this->activeAutomation('list_added', ['list_ids' => [$list->uuid]], [$this->waitStep(1)]);
        app(TriggerDispatcher::class)->start($automation, $this->contact()->id);

        $this->deleteJson("/api/v1/automations/{$automation->uuid}")->assertStatus(409);
        $this->patchJson("/api/v1/automations/{$automation->uuid}", ['trigger' => ['type' => 'list_added', 'config' => ['list_ids' => []]]])->assertStatus(409);
        $this->patchJson("/api/v1/automations/{$automation->uuid}", ['name' => 'Renommée'])->assertOk();
        $this->putJson("/api/v1/automations/{$automation->uuid}/steps", ['steps' => [$this->step('end')]])->assertStatus(409);

        $this->postJson("/api/v1/automations/{$automation->uuid}/pause")->assertOk()->assertJsonPath('data.status', 'paused');
        $this->assertDatabaseHas('audit_logs', ['action' => 'automation.paused', 'auditable_id' => $automation->id]);
        $this->postJson("/api/v1/automations/{$automation->uuid}/pause")->assertStatus(409);

        $this->postJson("/api/v1/automations/{$automation->uuid}/activate")->assertOk()->assertJsonPath('data.status', 'active');
        $this->postJson("/api/v1/automations/{$automation->uuid}/pause")->assertOk();
        $this->deleteJson("/api/v1/automations/{$automation->uuid}")->assertNoContent();
        $this->assertSoftDeleted('automations', ['id' => $automation->id]);
        $this->getJson("/api/v1/automations/{$automation->uuid}")->assertNotFound();
    }

    public function test_duplicate_copies_trigger_and_tree_as_draft(): void
    {
        $this->actingAsManager();
        $list = $this->list();
        $automation = $this->activeAutomation('list_added', ['list_ids' => [$list->uuid]], [
            $this->step('condition', ['rules' => ['version' => 2, 'type' => 'group', 'operator' => 'and', 'children' => []]], [$this->waitStep(1)], [$this->step('end')]),
        ]);

        $copy = $this->postJson("/api/v1/automations/{$automation->uuid}/duplicate")
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.trigger.config.list_ids', [$list->uuid])
            ->assertJsonPath('data.steps.0.yes.0.type', 'wait')
            ->assertJsonPath('data.steps.0.no.0.type', 'end')
            ->json('data');

        $this->assertNotSame($automation->uuid, $copy['id']);
        $this->assertSame(3, AutomationStep::query()->where('automation_id', Automation::query()->where('uuid', $copy['id'])->value('id'))->count());
    }

    public function test_runs_list_and_manual_exit(): void
    {
        $this->actingAsManager();
        $automation = $this->activeAutomation('list_added', ['list_ids' => []], [$this->waitStep(1), $this->step('end')]);
        $dispatcher = app(TriggerDispatcher::class);
        $waiting = $dispatcher->start($automation, $this->contact()->id);
        $dispatcher->start($automation, $this->contact()->id);
        $dispatcher->start($automation, $this->contact()->id);

        $this->getJson("/api/v1/automations/{$automation->uuid}/runs?per_page=2")->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.status', 'waiting')
            ->assertJsonPath('data.0.current_step_id', $this->stepAt($automation, 0)->uuid)
            ->assertJsonStructure(['data' => [['id', 'recipient' => ['id', 'email'], 'status', 'current_step_id', 'next_run_at', 'started_at', 'finished_at', 'error']], 'links', 'meta' => ['next_cursor', 'prev_cursor', 'per_page', 'path']]);

        $this->postJson("/api/v1/automations/{$automation->uuid}/runs/{$waiting->uuid}/exit")
            ->assertOk()->assertJsonPath('data.status', 'exited');
        $this->postJson("/api/v1/automations/{$automation->uuid}/runs/{$waiting->uuid}/exit")->assertStatus(409);

        $this->getJson("/api/v1/automations/{$automation->uuid}/runs?status=exited")->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $waiting->uuid);
        $this->getJson("/api/v1/automations/{$automation->uuid}")->assertOk()
            ->assertJsonPath('data.stats', ['started' => 3, 'in_progress' => 2, 'completed' => 0, 'exited' => 1])
            ->assertJsonPath('data.steps.0.stats', ['entered' => 3, 'completed' => 0]);
    }

    public function test_activity_reports_runs_and_per_email_stats(): void
    {
        $this->actingAsManager();
        $template = $this->template('Bienvenue J+0');
        $automation = $this->activeAutomation('list_added', ['list_ids' => []], [$this->sendEmailStep($template), $this->waitStep(1)]);
        $dispatcher = app(TriggerDispatcher::class);
        $contacts = collect(range(1, 4))->map(fn () => $this->contact());
        foreach ($contacts as $contact) {
            $dispatcher->start($automation, $contact->id);
        }

        $messages = Message::query()->orderBy('id')->get();
        $this->assertCount(4, $messages);
        foreach ($messages as $message) {
            $message->forceFill(['status' => 'delivered', 'sent_at' => now(), 'delivered_at' => now()])->save();
        }
        $recorder = app(TrackingEventRecorder::class);
        $recorder->recordOpen($messages[0]->refresh());
        $recorder->recordOpen($messages[0]->refresh());
        $recorder->recordOpen($messages[1]->refresh());
        $recorder->recordClick($messages[0]->refresh(), 'https://exemple.dz/a');
        $messages[3]->refresh()->forceFill(['status' => 'hard_bounced', 'delivered_at' => null, 'bounced_at' => now()])->save();

        $this->travel(1)->days();
        $this->travel(1)->minutes();
        dispatch(new AdvanceAutomationRunsJob);

        $activity = $this->getJson("/api/v1/automations/{$automation->uuid}/activity")->assertOk()
            ->assertJsonPath('data.started', 4)
            ->assertJsonPath('data.completed', 4)
            ->assertJsonPath('data.in_progress', 0)
            ->assertJsonPath('data.exited', 0)
            ->assertJsonPath('data.emails.0.step_id', $this->stepAt($automation, 0)->uuid)
            ->assertJsonPath('data.emails.0.template_name', 'Bienvenue J+0')
            ->json('data.emails.0');

        $stats = $activity['globalStats'];
        $this->assertSame(4, $stats['sent']);
        $this->assertSame(3, $stats['delivered']);
        $this->assertSame(1, $stats['hardBounces']);
        $this->assertSame(3, $stats['viewed']);
        $this->assertSame(2, $stats['uniqueViews']);
        $this->assertSame(1, $stats['uniqueClicks']);
        $this->assertEquals(66.67, $stats['opensRate']);
        $this->assertEquals(0.6667, $activity['rates']['open_rate']);
        $this->assertEquals(0.3333, $activity['rates']['click_rate']);
        $this->assertEquals(0.75, $activity['rates']['delivery_rate']);
        $this->assertEquals(0.5, $activity['rates']['click_to_open_rate']);

        $this->getJson("/api/v1/automations/{$automation->uuid}/activity?date_from=2000-01-01&date_to=2000-01-31")->assertOk()
            ->assertJsonPath('data.started', 0)
            ->assertJsonPath('data.emails.0.globalStats.sent', 0);

        $this->assertSame(4, AutomationRun::query()->where('status', 'completed')->count());
    }
}
