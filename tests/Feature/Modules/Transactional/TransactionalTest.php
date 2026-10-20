<?php

namespace Tests\Feature\Modules\Transactional;

use App\Domain\Enums\MessageStatus;
use App\Domain\Enums\RoleName;
use App\Modules\DeliveryEngine\Jobs\SendEmailJob;
use App\Modules\Identity\Models\User;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Senders\Models\Sender;
use App\Modules\Templates\Models\Template;
use App\Modules\Tracking\Models\Message;
use App\Modules\Tracking\Models\MessageEvent;
use App\Modules\Transactional\Contracts\TransactionalSender;
use App\Modules\Transactional\Services\ApiKeyService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class TransactionalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Queue::fake();
        RateLimiter::clear('x');
    }

    private function admin(): User
    {
        return User::factory()->withRole(RoleName::Administrator)->create();
    }

    /** @param list<string> $scopes */
    private function key(array $scopes, ?User $owner = null): string
    {
        [, $plain] = app(ApiKeyService::class)->create($owner ?? $this->admin(), ['name' => 'ERP', 'scopes' => $scopes]);

        return $plain;
    }

    /** @param array<string, mixed> $body */
    private function sendAs(string $plain, array $body, string $header = 'bearer')
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeaders($header === 'bearer' ? ['Authorization' => 'Bearer '.$plain] : ['api-key' => $plain])
            ->postJson('/api/v1/transactional/send', $body);
    }

    public function test_key_crud_shows_token_once_and_revokes_with_audit(): void
    {
        $admin = $this->admin();

        $res = $this->actingAs($admin)->postJson('/api/v1/api-keys', [
            'name' => 'ERP',
            'scopes' => ['transactional:send', 'contacts:read'],
        ])->assertCreated()
            ->assertJsonPath('data.api_key.name', 'ERP')
            ->assertJsonPath('data.api_key.scopes', ['transactional:send', 'contacts:read'])
            ->assertJsonPath('data.api_key.created_by.name', $admin->name);
        $plain = $res->json('data.plain_text_token');
        $id = $res->json('data.api_key.id');
        $this->assertStringStartsWith('pjm_', $plain);
        $this->assertSame(substr($plain, 0, 8), $res->json('data.api_key.token_prefix'));

        $list = $this->actingAs($admin)->getJson('/api/v1/api-keys')->assertOk()->assertJsonCount(1, 'data');
        $this->assertStringNotContainsString($plain, $list->getContent());

        $this->actingAs($admin)->postJson('/api/v1/api-keys', ['name' => 'X', 'scopes' => ['bogus']])
            ->assertStatus(422)->assertJsonValidationErrors('scopes.0');
        $this->actingAs($admin)->postJson('/api/v1/api-keys', ['name' => 'X', 'scopes' => []])->assertStatus(422);

        $this->actingAs($admin)->deleteJson("/api/v1/api-keys/$id")->assertNoContent();
        $this->actingAs($admin)->getJson('/api/v1/api-keys')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($admin)->deleteJson("/api/v1/api-keys/$id")->assertNotFound();

        $this->assertDatabaseHas('audit_logs', ['action' => 'api_key.created']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'api_key.revoked']);

        // A revoked key no longer authenticates.
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', 'Bearer '.$plain)->getJson('/api/v1/transactional/logs')->assertUnauthorized();
    }

    public function test_key_management_requires_permission_and_rejects_keys(): void
    {
        $manager = User::factory()->withRole(RoleName::MarketingManager)->create();
        $this->actingAs($manager)->getJson('/api/v1/api-keys')->assertForbidden();
        $this->actingAs($manager)->postJson('/api/v1/api-keys', ['name' => 'X', 'scopes' => ['contacts:read']])->assertForbidden();

        $plain = $this->key(['transactional:send']);
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', 'Bearer '.$plain)->getJson('/api/v1/api-keys')->assertForbidden();
    }

    public function test_send_with_html_via_bearer_and_api_key_header(): void
    {
        $plain = $this->key(['transactional:send']);

        $res = $this->sendAs($plain, [
            'to' => [['email' => 'Amina@Exemple.dz', 'name' => 'Amina']],
            'subject' => 'Commande {{ params.ORDER_ID }}',
            'html_content' => '<p>Commande {{ params.ORDER_ID }} <b>{{params.NOTE}}</b></p>',
            'params' => ['ORDER_ID' => '1042', 'NOTE' => '<x>'],
            'tags' => ['commande'],
        ])->assertCreated();

        $ids = $res->json('data.message_ids');
        $this->assertCount(1, $ids);
        $this->assertSame('<'.$ids[0].'@pagesjaunes-mailer>', $res->json('data.message_id'));

        $message = Message::query()->where('uuid', $ids[0])->firstOrFail();
        $this->assertSame('Commande 1042', $message->subject);
        $this->assertStringContainsString('Commande 1042', $message->html_body);
        $this->assertStringContainsString('&lt;x&gt;', $message->html_body);
        $this->assertNull($message->campaign_id);
        $this->assertFalse((bool) $message->is_test);
        Queue::assertPushed(SendEmailJob::class, fn (SendEmailJob $j) => $j->messageId === $message->id);

        $recipient = Recipient::where('email', 'amina@exemple.dz')->firstOrFail();
        $this->assertSame('api', $recipient->source);
        $queued = MessageEvent::where('message_id', $message->id)->where('event_type', 'queued')->firstOrFail();
        $this->assertSame('api', $queued->metadata['source']);
        $this->assertSame(['commande'], $queued->metadata['tags']);

        // Brevo-style header, existing recipient untouched.
        $recipient->update(['source' => 'manual']);
        $this->sendAs($plain, ['to' => [['email' => 'amina@exemple.dz']], 'subject' => 'S', 'html_content' => '<p>x</p>'], 'header')
            ->assertCreated();
        $this->assertSame('manual', $recipient->fresh()->source);
        $this->assertSame(1, Recipient::where('email', 'amina@exemple.dz')->count());

        $this->assertDatabaseHas('audit_logs', ['action' => 'transactional.sent']);
    }

    public function test_send_with_template_and_verified_sender(): void
    {
        $template = Template::factory()->transactional()->create(['html_content' => '<p>Bonjour {{ params.NAME }}</p>', 'subject' => 'Bienvenue']);
        $sender = Sender::factory()->verified()->create(['email' => 'no-reply@exemple.dz']);
        $plain = $this->key(['transactional:send']);

        $res = $this->sendAs($plain, [
            'to' => [['email' => 'a@exemple.dz'], ['email' => 'b@exemple.dz'], ['email' => 'A@exemple.dz']],
            'template_id' => $template->uuid,
            'sender_id' => $sender->uuid,
            'params' => ['NAME' => 'Amina'],
        ])->assertCreated();

        $this->assertCount(2, $res->json('data.message_ids'));
        $message = Message::where('uuid', $res->json('data.message_ids.0'))->firstOrFail();
        $this->assertSame('Bienvenue', $message->subject);
        $this->assertStringContainsString('Bonjour Amina', $message->html_body);
        $queued = MessageEvent::where('message_id', $message->id)->where('event_type', 'queued')->firstOrFail();
        $this->assertSame($template->uuid, $queued->metadata['template_id']);
        $this->assertSame('no-reply@exemple.dz', $queued->metadata['sender_email']);
        Queue::assertPushed(SendEmailJob::class, 2);
    }

    public function test_validation_errors(): void
    {
        $plain = $this->key(['transactional:send']);
        $ok = ['subject' => 'S', 'html_content' => '<p>x</p>'];

        $this->sendAs($plain, $ok)->assertStatus(422)->assertJsonValidationErrors('to');
        $this->sendAs($plain, $ok + ['to' => []])->assertStatus(422)->assertJsonValidationErrors('to');

        $many = array_map(fn (int $i) => ['email' => "u$i@exemple.dz"], range(1, 51));
        $this->sendAs($plain, $ok + ['to' => $many])->assertStatus(422)->assertJsonValidationErrors('to');
        $this->sendAs($plain, $ok + ['to' => array_slice($many, 0, 50)])->assertCreated();

        $to = ['to' => [['email' => 'a@exemple.dz']]];
        $unverified = Sender::factory()->create();
        $this->sendAs($plain, $ok + $to + ['sender_id' => $unverified->uuid])->assertStatus(422)->assertJsonValidationErrors('sender_id');
        $this->sendAs($plain, $ok + $to + ['sender' => ['email' => $unverified->email]])->assertStatus(422)->assertJsonValidationErrors('sender.email');

        $this->sendAs($plain, $to + ['html_content' => '<p>x</p>'])->assertStatus(422)->assertJsonValidationErrors('subject');
        $this->sendAs($plain, $to + ['subject' => 'S'])->assertStatus(422)->assertJsonValidationErrors('html_content');
        $marketing = Template::factory()->create(['subject' => 'S']);
        $this->sendAs($plain, $to + ['template_id' => $marketing->uuid])->assertStatus(422)->assertJsonValidationErrors('template_id');
        $this->sendAs($plain, $to + $ok + ['template_id' => $marketing->uuid])->assertStatus(422);
        $this->sendAs($plain, ['to' => [['email' => 'not-an-email']]] + $ok)->assertStatus(422)->assertJsonValidationErrors('to.0.email');
    }

    public function test_scope_is_enforced_and_session_permission_applies(): void
    {
        $body = ['to' => [['email' => 'a@exemple.dz']], 'subject' => 'S', 'html_content' => '<p>x</p>'];

        $readOnly = $this->key(['contacts:read']);
        $this->sendAs($readOnly, $body)->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', 'Bearer '.$readOnly)->getJson('/api/v1/transactional/logs')->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', 'Bearer nope')->postJson('/api/v1/transactional/send', $body)->assertUnauthorized();

        $operator = User::factory()->withRole(RoleName::MarketingOperator)->create();
        $this->actingAs($operator)->postJson('/api/v1/transactional/send', $body)->assertForbidden();
        $manager = User::factory()->withRole(RoleName::MarketingManager)->create();
        $this->actingAs($manager)->postJson('/api/v1/transactional/send', $body)->assertCreated();
    }

    public function test_last_used_is_updated(): void
    {
        $admin = $this->admin();
        $plain = $this->key(['transactional:send'], $admin);
        $this->sendAs($plain, ['to' => [['email' => 'a@exemple.dz']], 'subject' => 'S', 'html_content' => '<p>x</p>'])->assertCreated();

        $this->app['auth']->forgetGuards();
        $this->actingAs($admin);
        $row = $this->getJson('/api/v1/api-keys')->assertOk()->json('data.0');
        $this->assertNotNull($row['last_used_at']);
        $this->assertSame('127.0.0.1', $row['last_used_ip']);
    }

    public function test_rate_limit_per_key_returns_429(): void
    {
        config(['transactional.key_rate_limit' => 2]);
        $plain = $this->key(['transactional:send']);
        $body = ['to' => [['email' => 'a@exemple.dz']], 'subject' => 'S', 'html_content' => '<p>x</p>'];

        $this->sendAs($plain, $body)->assertCreated();
        $this->sendAs($plain, $body)->assertCreated();
        $this->sendAs($plain, $body)->assertStatus(429)->assertHeader('Retry-After');
        $other = $this->key(['transactional:send']);
        $this->sendAs($other, $body)->assertCreated();
    }

    public function test_logs_filters_search_cursor_and_event_timeline(): void
    {
        $plain = $this->key(['transactional:send', 'transactional:read']);
        $this->sendAs($plain, ['to' => [['email' => 'amina@exemple.dz']], 'subject' => 'Facture 7', 'html_content' => '<p>x</p>', 'tags' => ['facture']])->assertCreated();
        $this->sendAs($plain, ['to' => [['email' => 'karim@exemple.dz']], 'subject' => 'Bienvenue', 'html_content' => '<p>x</p>'])->assertCreated();
        $first = Message::query()->where('subject', 'Facture 7')->firstOrFail();
        $first->markStatus(MessageStatus::Delivered);
        $first->markStatus(MessageStatus::Opened);
        // Campaign and test messages are not transactional logs.
        Message::factory()->create(['campaign_id' => null, 'is_test' => true]);

        $get = function (string $qs = '') use ($plain) {
            $this->app['auth']->forgetGuards();

            return $this->withHeader('Authorization', 'Bearer '.$plain)->getJson('/api/v1/transactional/logs'.$qs);
        };

        $all = $get()->assertOk()->assertJsonCount(2, 'data');
        $all->assertJsonPath('data.1.recipient_email', 'amina@exemple.dz')
            ->assertJsonPath('data.1.last_event', 'opened')
            ->assertJsonPath('data.1.source', 'api')
            ->assertJsonPath('data.1.tags', ['facture'])
            ->assertJsonPath('data.0.last_event', null);

        $get('?event=opened')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.subject', 'Facture 7');
        $get('?event=delivered')->assertOk()->assertJsonCount(1, 'data');
        $get('?event=hard_bounce')->assertOk()->assertJsonCount(0, 'data');
        $get('?event=nope')->assertStatus(422);
        $get('?q=karim')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.subject', 'Bienvenue');
        $get('?q=Facture')->assertOk()->assertJsonCount(1, 'data');
        $get('?q='.urlencode('<'.$first->uuid.'@pagesjaunes-mailer>'))->assertOk()->assertJsonCount(1, 'data');
        $get('?tag=facture')->assertOk()->assertJsonCount(1, 'data');
        $get('?source=api')->assertOk()->assertJsonCount(2, 'data');
        $get('?source=composer')->assertOk()->assertJsonCount(0, 'data');

        $page = $get('?per_page=1')->assertOk()->assertJsonCount(1, 'data');
        $this->assertNotNull($page->json('links.next') ?? $page->json('meta.next_cursor'));

        $detail = $get('/'.$first->uuid)->assertOk();
        $detail->assertJsonPath('data.id', $first->uuid);
        $events = array_column($detail->json('data.events'), 'event');
        $this->assertSame(['delivered', 'opened'], $events);
        $get('/00000000-0000-4000-8000-000000000000')->assertNotFound();
    }

    public function test_logs_and_settings_for_session_users(): void
    {
        $manager = User::factory()->withRole(RoleName::MarketingManager)->create();
        $operator = User::factory()->withRole(RoleName::MarketingOperator)->create();

        $this->actingAs($operator)->getJson('/api/v1/transactional/logs')->assertForbidden();
        $this->actingAs($manager)->getJson('/api/v1/transactional/logs')->assertOk();
        $this->actingAs($manager)->getJson('/api/v1/transactional/settings')->assertOk()
            ->assertJsonPath('data.rate_limit_per_minute', 600)
            ->assertJsonPath('data.smtp_relay.enabled', false);
    }

    public function test_contract_is_bound_and_automation_source_is_not_audited(): void
    {
        $sender = app(TransactionalSender::class);
        $messages = $sender->send([
            'to' => [['email' => 'z@exemple.dz']], 'subject' => 'S', 'html_content' => '<p>x</p>',
        ], ['source' => 'automation']);

        $this->assertCount(1, $messages);
        $this->assertInstanceOf(Message::class, $messages[0]);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'transactional.sent']);
        $plain = $this->key(['transactional:read']);
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', 'Bearer '.$plain)->getJson('/api/v1/transactional/logs?source=automation')
            ->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_settings_web_route_is_permission_gated(): void
    {
        $this->actingAs($this->admin())->get('/transactional/settings')->assertOk()
            ->assertInertia(fn ($page) => $page->component('Transactional/Settings', false));

        $manager = User::factory()->withRole(RoleName::MarketingManager)->create();
        $this->actingAs($manager)->get('/transactional/settings')->assertOk();

        $operator = User::factory()->withRole(RoleName::MarketingOperator)->create();
        $this->actingAs($operator)->get('/transactional/settings')->assertForbidden();
        $this->assertSame('/transactional/settings', route('transactional.settings', absolute: false));
    }
}
