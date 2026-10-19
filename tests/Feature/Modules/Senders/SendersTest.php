<?php

namespace Tests\Feature\Modules\Senders;

use App\Domain\Enums\RoleName;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Identity\Models\User;
use App\Modules\Senders\Contracts\DnsResolverContract;
use App\Modules\Senders\Jobs\VerifyDomainJob;
use App\Modules\Senders\Mail\SenderVerificationMail;
use App\Modules\Senders\Models\Sender;
use App\Modules\Senders\Models\SendingDomain;
use Database\Seeders\DefaultSenderSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

use Tests\Support\FakeDnsResolver;
use Tests\TestCase;

class SendersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        FakeDnsResolver::reset();
        $this->app->bind(DnsResolverContract::class, FakeDnsResolver::class);
    }

    private function manager(): User
    {
        return User::factory()->withRole(RoleName::MarketingManager)->create();
    }

    public function test_crud_flow_and_verification_mail_queued(): void
    {
        Mail::fake();
        $m = $this->manager();

        $id = $this->actingAs($m)->postJson('/api/v1/senders', ['name' => 'News', 'email' => 'News@exemple.dz'])
            ->assertCreated()
            ->assertJsonPath('data.email', 'news@exemple.dz')
            ->assertJsonPath('data.domain', 'exemple.dz')
            ->assertJsonPath('data.is_verified', false)
            ->assertJsonPath('data.domain_status', null)
            ->json('data.id');
        Mail::assertQueued(SenderVerificationMail::class);
        $this->assertNotNull(Sender::first()->verification_sent_at);

        $this->actingAs($m)->getJson("/api/v1/senders/$id")->assertOk();
        $this->actingAs($m)->patchJson("/api/v1/senders/$id", ['name' => 'Autre', 'reply_to' => 'r@exemple.dz'])
            ->assertOk()->assertJsonPath('data.name', 'Autre')->assertJsonPath('data.reply_to', 'r@exemple.dz');
        $this->actingAs($m)->patchJson("/api/v1/senders/$id", ['email' => 'x@exemple.dz'])
            ->assertStatus(422)->assertJsonValidationErrors('email');
        $this->actingAs($m)->getJson('/api/v1/senders?q=Autre')->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($m)->getJson('/api/v1/senders?verified=1')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($m)->deleteJson("/api/v1/senders/$id")->assertNoContent();
        $this->assertSoftDeleted('senders', ['uuid' => $id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'sender.created']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'sender.deleted']);
    }

    public function test_viewer_is_forbidden_and_operator_can_only_list_senders(): void
    {
        $viewer = User::factory()->withRole(RoleName::Viewer)->create();
        $sender = Sender::factory()->create();
        $this->actingAs($viewer)->getJson('/api/v1/senders')->assertForbidden();
        $this->actingAs($viewer)->postJson('/api/v1/senders', ['name' => 'A', 'email' => 'a@b.dz'])->assertForbidden();
        $this->actingAs($viewer)->deleteJson("/api/v1/senders/{$sender->uuid}")->assertForbidden();
        $this->actingAs($viewer)->getJson('/api/v1/sending-domains')->assertForbidden();
        $this->actingAs($viewer)->postJson('/api/v1/sending-domains', ['domain' => 'exemple.dz'])->assertForbidden();

        $operator = User::factory()->withRole(RoleName::MarketingOperator)->create();
        $this->actingAs($operator)->getJson('/api/v1/senders')->assertOk();
        $this->actingAs($operator)->postJson('/api/v1/senders', ['name' => 'A', 'email' => 'a@b.dz'])->assertForbidden();
        $this->actingAs($operator)->getJson('/api/v1/sending-domains')->assertForbidden();
    }

    public function test_verification_link_flow(): void
    {
        Mail::fake();
        $sender = Sender::factory()->create();
        $url = URL::temporarySignedRoute('senders.verify', now()->addHours(48), ['id' => $sender->uuid]);

        $this->get($url)->assertRedirect('/settings/senders?verified='.$sender->uuid);
        $sender->refresh();
        $this->assertTrue($sender->isVerified());
        $this->assertSame('email_link', $sender->verification_method);
        $this->assertDatabaseHas('audit_logs', ['action' => 'sender.verified']);

        $other = Sender::factory()->create();
        $this->get("/api/v1/senders/{$other->uuid}/verify")->assertForbidden();
        $this->assertFalse($other->refresh()->isVerified());
        $expired = URL::temporarySignedRoute('senders.verify', now()->subMinute(), ['id' => $other->uuid]);
        $this->get($expired)->assertForbidden();
    }

    public function test_resend_verification_conflict_and_rate_limit(): void
    {
        Mail::fake();
        $m = $this->manager();
        $verified = Sender::factory()->verified()->create();
        $this->actingAs($m)->postJson("/api/v1/senders/{$verified->uuid}/resend-verification")->assertStatus(409);

        $sender = Sender::factory()->create();
        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($m)->postJson("/api/v1/senders/{$sender->uuid}/resend-verification")->assertStatus(202);
        }
        $this->actingAs($m)->postJson("/api/v1/senders/{$sender->uuid}/resend-verification")->assertStatus(429);
        Mail::assertQueuedCount(3);
    }

    public function test_default_sender_switch_and_constraints(): void
    {
        $m = $this->manager();
        $a = Sender::factory()->default()->create();
        $b = Sender::factory()->verified()->create();
        $c = Sender::factory()->create();

        $this->actingAs($m)->postJson("/api/v1/senders/{$c->uuid}/default")->assertStatus(409);
        $this->actingAs($m)->postJson("/api/v1/senders/{$b->uuid}/default")->assertOk()->assertJsonPath('data.is_default', true);
        $this->assertSame(1, Sender::where('is_default', true)->count());
        $this->assertFalse($a->refresh()->is_default);
        $this->actingAs($m)->deleteJson("/api/v1/senders/{$b->uuid}")->assertStatus(409);
    }

    public function test_sender_on_verified_domain_is_auto_verified(): void
    {
        Mail::fake();
        $m = $this->manager();
        SendingDomain::factory()->verified()->create(['domain' => 'verifie.dz']);

        $this->actingAs($m)->postJson('/api/v1/senders', ['name' => 'V', 'email' => 'a@verifie.dz', 'is_default' => true])
            ->assertCreated()
            ->assertJsonPath('data.is_verified', true)
            ->assertJsonPath('data.verification_method', 'domain')
            ->assertJsonPath('data.domain_status', 'verified')
            ->assertJsonPath('data.is_default', true);
        Mail::assertNothingQueued();
    }

    public function test_domain_record_generation(): void
    {
        $m = $this->manager();
        $res = $this->actingAs($m)->postJson('/api/v1/sending-domains', ['domain' => 'Exemple.dz'])
            ->assertCreated()
            ->assertJsonPath('data.domain', 'exemple.dz')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.has_private_key', true)
            ->assertJsonPath('data.is_authenticated', false)
            ->assertJsonCount(3, 'data.records')
            ->assertJsonPath('data.records.1.host', 'pjm1._domainkey.exemple.dz');
        $this->assertStringStartsWith('v=DKIM1; k=rsa; p=MII', $res->json('data.records.1.value'));
        $this->assertStringStartsWith('v=spf1 include:', $res->json('data.records.0.value'));
        $this->assertStringStartsWith('v=DMARC1; p=none; rua=mailto:', $res->json('data.records.2.value'));
        $this->assertStringNotContainsString('PRIVATE', $res->getContent());

        $domain = SendingDomain::first();
        $this->assertStringContainsString('PRIVATE KEY', $domain->dkim_private_key);
        $this->assertStringNotContainsString('PRIVATE', (string) $domain->getRawOriginal('dkim_private_key'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'domain.created']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'domain.dkim_generated']);

        $this->actingAs($m)->postJson('/api/v1/sending-domains', ['domain' => 'exemple.dz'])->assertStatus(422);
        $this->actingAs($m)->postJson('/api/v1/sending-domains', ['domain' => 'pas un domaine'])->assertStatus(422);
        $this->actingAs($m)->getJson('/api/v1/sending-domains')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_verify_endpoint_dispatches_job(): void
    {
        Bus::fake();
        $m = $this->manager();
        $d = SendingDomain::factory()->create();
        $this->actingAs($m)->postJson("/api/v1/sending-domains/{$d->uuid}/verify")
            ->assertStatus(202)->assertJsonPath('data.status', 'checking');
        Bus::assertDispatched(VerifyDomainJob::class, fn ($j) => $j->domainId === $d->id);
    }

    public function test_verify_job_pass_and_fail_per_record(): void
    {
        Mail::fake();
        $m = $this->manager();
        $id = $this->actingAs($m)->postJson('/api/v1/sending-domains', ['domain' => 'exemple.dz'])->json('data.id');
        $domain = SendingDomain::where('uuid', $id)->first();
        $pending = Sender::factory()->create(['email' => 'x@exemple.dz', 'domain_id' => $domain->id]);
        $records = collect($domain->records)->keyBy('kind');

        // Nothing published: failed.
        (new VerifyDomainJob($domain->id))->handle(app(\App\Modules\Senders\Services\DomainAuthService::class));
        $domain->refresh();
        $this->assertSame('failed', $domain->status);
        $this->assertSame('missing', $domain->recordStatus('spf'));
        $this->assertFalse($pending->refresh()->isVerified());

        // SPF valid, DKIM wrong, DMARC invalid.
        FakeDnsResolver::$txt['exemple.dz'] = [$records['spf']['value']];
        FakeDnsResolver::$txt['pjm1._domainkey.exemple.dz'] = ['v=DKIM1; k=rsa; p=WRONG'];
        FakeDnsResolver::$txt['_dmarc.exemple.dz'] = ['v=DMARC1'];
        (new VerifyDomainJob($domain->id))->handle(app(\App\Modules\Senders\Services\DomainAuthService::class));
        $domain->refresh();
        $this->assertSame('partially_verified', $domain->status);
        $this->assertSame('valid', $domain->recordStatus('spf'));
        $this->assertSame('invalid', $domain->recordStatus('dkim'));
        $this->assertSame('invalid', $domain->recordStatus('dmarc'));
        $this->assertFalse($domain->isAuthenticated());

        // All published: verified, sender auto-verified.
        FakeDnsResolver::$txt['pjm1._domainkey.exemple.dz'] = [$records['dkim']['value']];
        FakeDnsResolver::$txt['_dmarc.exemple.dz'] = [$records['dmarc']['value']];
        (new VerifyDomainJob($domain->id))->handle(app(\App\Modules\Senders\Services\DomainAuthService::class));
        $domain->refresh();
        $this->assertSame('verified', $domain->status);
        $this->assertTrue($domain->isAuthenticated());
        $this->assertNotNull($domain->last_checked_at);
        $this->assertSame('valid', $domain->recordStatus('dmarc'));
        $this->assertTrue($pending->refresh()->isVerified());
        $this->assertSame('domain', $pending->verification_method);
        $this->assertGreaterThan(0, AuditLog::where('action', 'domain.verified')->count());

        $this->actingAs($m)->deleteJson("/api/v1/sending-domains/{$id}")->assertStatus(409);
        $pending->delete();
        $this->actingAs($m)->deleteJson("/api/v1/sending-domains/{$id}")->assertNoContent();
    }

    public function test_dkim_cname_delegation_counts_as_valid(): void
    {
        $d = SendingDomain::factory()->create();
        FakeDnsResolver::$cname['pjm1._domainkey.'.$d->domain] = ['pjm1.dkim.example.test'];
        FakeDnsResolver::$txt['pjm1.dkim.example.test'] = [collect($d->records)->firstWhere('kind', 'dkim')['value']];
        app(\App\Modules\Senders\Services\DomainAuthService::class)->runChecks($d);
        $this->assertSame('valid', $d->refresh()->recordStatus('dkim'));
    }

    public function test_dkim_cname_to_wrong_or_empty_target_is_invalid(): void
    {
        $d = SendingDomain::factory()->create();
        FakeDnsResolver::$cname['pjm1._domainkey.'.$d->domain] = ['pjm1.dkim.example.test'];
        app(\App\Modules\Senders\Services\DomainAuthService::class)->runChecks($d);
        $this->assertSame('invalid', $d->refresh()->recordStatus('dkim'));

        FakeDnsResolver::$txt['pjm1.dkim.example.test'] = ['v=DKIM1; k=rsa; p=WRONGKEY'];
        app(\App\Modules\Senders\Services\DomainAuthService::class)->runChecks($d);
        $this->assertSame('invalid', $d->refresh()->recordStatus('dkim'));
    }

    public function test_deleted_sender_email_can_be_recreated(): void
    {
        Mail::fake();
        $m = $this->manager();
        $id = $this->actingAs($m)->postJson('/api/v1/senders', ['name' => 'Old', 'email' => 'again@exemple.dz'])->assertCreated()->json('data.id');
        $this->actingAs($m)->deleteJson("/api/v1/senders/$id")->assertNoContent();
        $this->assertSoftDeleted('senders', ['email' => 'again@exemple.dz']);

        $this->actingAs($m)->postJson('/api/v1/senders', ['name' => 'New', 'email' => 'again@exemple.dz'])
            ->assertCreated()->assertJsonPath('data.name', 'New');

        $this->assertSame(1, Sender::withTrashed()->where('email', 'again@exemple.dz')->count());
        $this->assertNull(Sender::where('email', 'again@exemple.dz')->first()->deleted_at);
        // An active duplicate is still refused.
        $this->actingAs($m)->postJson('/api/v1/senders', ['name' => 'Dup', 'email' => 'again@exemple.dz'])->assertStatus(422);
    }

    /** @dataProvider blockingStatuses */
    public function test_sender_in_use_by_campaign_in_status_cannot_be_deleted(string $status, bool $blocked): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasColumn('campaigns', 'sender_id')) {
            $this->markTestSkipped('campaigns.sender_id missing');
        }
        $m = $this->manager();
        $sender = Sender::factory()->create();
        \Illuminate\Support\Facades\DB::table('campaigns')->insert([
            'uuid' => (string) \Illuminate\Support\Str::uuid(), 'name' => 'C', 'subject' => 'S', 'html_body' => '<p>x</p>',
            'status' => $status, 'sender_id' => $sender->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($m)->deleteJson("/api/v1/senders/{$sender->uuid}")->assertStatus($blocked ? 409 : 204);
    }

    /** @return array<string, array{string, bool}> */
    public static function blockingStatuses(): array
    {
        return [
            'scheduled' => ['scheduled', true], 'running' => ['running', true], 'paused' => ['paused', true],
            'suspended' => ['suspended', true], 'draft' => ['draft', false],
        ];
    }

    public function test_default_sender_migration_is_idempotent_and_seeds(): void
    {
        config(['mail.from.address' => 'noreply@prod.dz', 'mail.from.name' => 'Prod']);
        $migration = require database_path('migrations/2026_10_20_100003_seed_default_sender.php');
        $migration->up(); // no-op under the testing environment
        $this->assertSame(0, Sender::count());

        $migration->seed();
        $migration->seed();

        $this->assertSame(1, Sender::count());
        $this->assertTrue(Sender::first()->is_default);
    }

    public function test_default_sender_seeder_is_idempotent(): void
    {
        config(['mail.from.address' => 'Hello@Site.dz', 'mail.from.name' => 'Site']);
        $this->seed(DefaultSenderSeeder::class);
        $this->seed(DefaultSenderSeeder::class);

        $this->assertSame(1, Sender::count());
        $s = Sender::first();
        $this->assertSame('hello@site.dz', $s->email);
        $this->assertSame('system', $s->verification_method);
        $this->assertTrue($s->is_default);
        $this->assertTrue($s->isVerified());
    }
}
