<?php

namespace Tests\Feature\Modules\Webhooks;

use App\Domain\Enums\RoleName;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Identity\Models\User;
use App\Modules\Webhooks\Jobs\DeliverWebhookJob;
use App\Modules\Webhooks\Models\ApiWebhook;
use App\Modules\Webhooks\Models\ApiWebhookDelivery;
use App\Modules\Webhooks\Services\WebhookDispatcher;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

class WebhookApiTest extends WebhookTestCase
{
    public function test_crud_flow_with_one_time_secret_and_audit(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->postJson('/api/v1/webhooks', [
            'url' => 'https://hooks.exemple.dz/mail',
            'description' => 'ERP',
            'type' => 'transactional',
            'events' => ['sent', 'delivered', 'delivered', 'hardBounce'],
            'headers' => ['X-Api-Token' => 'abc'],
        ])->assertCreated();

        $secret = $response->json('data.secret');
        $id = $response->json('data.webhook.id');
        $this->assertStringStartsWith('whsec_', $secret);
        $this->assertSame(['request', 'delivered', 'hardBounce'], $response->json('data.webhook.events'));
        $this->assertSame('…'.substr($secret, -4), $response->json('data.webhook.secret_hint'));
        $this->assertSame(['X-Api-Token' => 'abc'], $response->json('data.webhook.headers'));
        $this->assertTrue($response->json('data.webhook.is_active'));
        $this->assertSame(0, $response->json('data.webhook.failure_count'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'webhook.created']);

        // The stored secret is encrypted at rest and never returned again.
        $this->assertNotSame($secret, \DB::table('api_webhooks')->value('secret'));
        $this->actingAs($admin)->getJson("/api/v1/webhooks/$id")->assertOk()->assertJsonMissingPath('data.secret');
        $this->actingAs($admin)->getJson('/api/v1/webhooks')->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($admin)->getJson('/api/v1/webhooks?type=marketing')->assertOk()->assertJsonCount(0, 'data');

        $this->actingAs($admin)->patchJson("/api/v1/webhooks/$id", ['events' => ['click'], 'is_active' => false])
            ->assertOk()->assertJsonPath('data.events', ['click'])->assertJsonPath('data.is_active', false);

        $rotated = $this->actingAs($admin)->postJson("/api/v1/webhooks/$id/rotate-secret")->assertOk();
        $this->assertNotSame($secret, $rotated->json('data.secret'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'webhook.secret_rotated']);

        $this->actingAs($admin)->deleteJson("/api/v1/webhooks/$id")->assertNoContent();
        $this->assertSame(0, ApiWebhook::query()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'webhook.deleted']);
        $this->assertSame(2, AuditLog::query()->whereIn('action', ['webhook.created', 'webhook.deleted'])->count());
        $this->actingAs($admin)->getJson("/api/v1/webhooks/$id")->assertNotFound();
    }

    public function test_url_guard_rejects_private_hosts_and_unresolvable_hosts(): void
    {
        $admin = $this->admin();
        $base = ['type' => 'transactional', 'events' => ['delivered']];

        $this->actingAs($admin)->postJson('/api/v1/webhooks', $base + ['url' => 'https://intranet.exemple.dz/hook'])
            ->assertStatus(422)->assertJsonValidationErrors('url');
        $this->actingAs($admin)->postJson('/api/v1/webhooks', $base + ['url' => 'https://127.0.0.1/hook'])
            ->assertStatus(422)->assertJsonValidationErrors('url');
        $this->actingAs($admin)->postJson('/api/v1/webhooks', $base + ['url' => 'https://nulle-part.exemple.dz/hook'])
            ->assertStatus(422)->assertJsonValidationErrors('url');
        $this->actingAs($admin)->postJson('/api/v1/webhooks', $base + ['url' => 'ftp://hooks.exemple.dz/hook'])
            ->assertStatus(422)->assertJsonValidationErrors('url');

        $existing = ApiWebhook::factory()->create();
        $this->actingAs($admin)->patchJson("/api/v1/webhooks/{$existing->uuid}", ['url' => 'https://10.1.2.3/x'])
            ->assertStatus(422)->assertJsonValidationErrors('url');
        $this->assertSame(1, ApiWebhook::query()->count());
    }

    public function test_events_must_match_the_webhook_type(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->postJson('/api/v1/webhooks', [
            'url' => 'https://hooks.exemple.dz/a', 'type' => 'marketing', 'events' => ['listAddition', 'proxyOpen'],
        ])->assertStatus(422)->assertJsonValidationErrors('events.1');

        $this->actingAs($admin)->postJson('/api/v1/webhooks', [
            'url' => 'https://hooks.exemple.dz/a', 'type' => 'transactional', 'events' => ['contactDeleted'],
        ])->assertStatus(422)->assertJsonValidationErrors('events.0');

        $this->actingAs($admin)->postJson('/api/v1/webhooks', [
            'url' => 'https://hooks.exemple.dz/a', 'type' => 'transactional', 'events' => [],
        ])->assertStatus(422)->assertJsonValidationErrors('events');

        // PATCH validates against the stored type when only events change.
        $marketing = ApiWebhook::factory()->marketing()->create();
        $this->actingAs($admin)->patchJson("/api/v1/webhooks/{$marketing->uuid}", ['events' => ['proxyOpen']])
            ->assertStatus(422)->assertJsonValidationErrors('events.0');

        $this->actingAs($admin)->postJson('/api/v1/webhooks', [
            'url' => 'https://hooks.exemple.dz/a', 'type' => 'transactional', 'events' => ['delivered'],
            'headers' => ['X-Mailer-Signature' => 'forged'],
        ])->assertStatus(422)->assertJsonValidationErrors('headers.X-Mailer-Signature');
    }

    public function test_permissions(): void
    {
        $webhook = ApiWebhook::factory()->create();
        $payload = ['url' => 'https://hooks.exemple.dz/a', 'type' => 'transactional', 'events' => ['delivered']];

        foreach ([$this->manager(), User::factory()->withRole(RoleName::Viewer)->create()] as $user) {
            $this->actingAs($user)->getJson('/api/v1/webhooks')->assertForbidden();
            $this->actingAs($user)->postJson('/api/v1/webhooks', $payload)->assertForbidden();
            $this->actingAs($user)->patchJson("/api/v1/webhooks/{$webhook->uuid}", ['is_active' => false])->assertForbidden();
            $this->actingAs($user)->deleteJson("/api/v1/webhooks/{$webhook->uuid}")->assertForbidden();
            $this->actingAs($user)->postJson("/api/v1/webhooks/{$webhook->uuid}/test", ['event' => 'delivered'])->assertForbidden();
            $this->actingAs($user)->getJson("/api/v1/webhooks/{$webhook->uuid}/deliveries")->assertForbidden();
        }
        $this->assertSame(1, ApiWebhook::query()->count());
    }

    public function test_test_endpoint_sends_a_signed_sample_synchronously(): void
    {
        Http::fake(['hooks.exemple.dz/*' => Http::response('ok', 200)]);
        $webhook = ApiWebhook::factory()->create(['secret' => 'whsec_test']);

        $response = $this->actingAs($this->admin())->postJson("/api/v1/webhooks/{$webhook->uuid}/test", ['event' => 'click'])
            ->assertOk()
            ->assertJsonPath('data.event', 'click')
            ->assertJsonPath('data.status', 'succeeded')
            ->assertJsonPath('data.response_status', 200)
            ->assertJsonPath('data.payload.event', 'click')
            ->assertJsonPath('data.payload.id', $webhook->uuid);

        $this->assertSame(1, $response->json('data.attempts'));
        Http::assertSent(function ($request) use ($webhook) {
            $timestamp = (int) $request->header('X-Mailer-Timestamp')[0];

            return $request->url() === $webhook->url
                && $request->header('X-Mailer-Event')[0] === 'click'
                && $request->header('X-Mailer-Signature')[0] === WebhookDispatcher::signature('whsec_test', $timestamp, $request->body());
        });

        $this->actingAs($this->admin())->postJson("/api/v1/webhooks/{$webhook->uuid}/test", ['event' => 'contactDeleted'])
            ->assertStatus(422)->assertJsonValidationErrors('event');
    }

    public function test_test_endpoint_failure_is_not_retried_and_not_counted(): void
    {
        Queue::fake();
        Http::fake(['*' => Http::response('boom', 500)]);
        $webhook = ApiWebhook::factory()->create();

        $this->actingAs($this->admin())->postJson("/api/v1/webhooks/{$webhook->uuid}/test", ['event' => 'delivered'])
            ->assertOk()->assertJsonPath('data.status', 'failed')->assertJsonPath('data.response_status', 500)
            ->assertJsonPath('data.response_body_excerpt', 'boom');

        Queue::assertNothingPushed();
        $this->assertSame(0, $webhook->fresh()->failure_count);
    }

    public function test_deliveries_list_is_cursor_paginated_filterable_and_redeliverable(): void
    {
        Queue::fake();
        $webhook = ApiWebhook::factory()->create();
        ApiWebhookDelivery::factory()->count(3)->for($webhook, 'webhook')->status('succeeded')->create();
        $failed = ApiWebhookDelivery::factory()->for($webhook, 'webhook')->status('failed')->create(['event' => 'click', 'attempts' => 6]);
        ApiWebhookDelivery::factory()->create(); // another webhook

        $admin = $this->admin();
        $page = $this->actingAs($admin)->getJson("/api/v1/webhooks/{$webhook->uuid}/deliveries?per_page=2")->assertOk();
        $page->assertJsonCount(2, 'data')->assertJsonStructure(['data', 'links', 'meta' => ['per_page', 'next_cursor']]);
        $this->assertNotNull($page->json('meta.next_cursor'));
        $this->assertSame($failed->uuid, $page->json('data.0.id'));
        $this->assertSame($webhook->uuid, $page->json('data.0.webhook_id'));

        $this->actingAs($admin)->getJson("/api/v1/webhooks/{$webhook->uuid}/deliveries?status=failed")->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($admin)->getJson("/api/v1/webhooks/{$webhook->uuid}/deliveries?event=click")->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($admin)->getJson("/api/v1/webhooks/{$webhook->uuid}/deliveries?status=nope")->assertStatus(422);

        $this->actingAs($admin)->postJson("/api/v1/webhook-deliveries/{$failed->uuid}/retry")
            ->assertStatus(202)->assertJsonPath('data.status', 'pending')->assertJsonPath('data.attempts', 0);
        Queue::assertPushed(DeliverWebhookJob::class, fn ($job) => $job->deliveryId === $failed->id);
        $this->actingAs($this->manager())->postJson("/api/v1/webhook-deliveries/{$failed->uuid}/retry")->assertForbidden();
    }
}
