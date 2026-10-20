<?php

namespace Tests\Feature\Modules\Webhooks;

use App\Modules\Identity\Models\User;
use App\Modules\Webhooks\Jobs\DeliverWebhookJob;
use App\Modules\Webhooks\Jobs\SweepWebhookDeliveriesJob;
use App\Modules\Webhooks\Models\ApiWebhook;
use App\Modules\Webhooks\Models\ApiWebhookDelivery;
use App\Modules\Webhooks\Services\WebhookDispatcher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

class WebhookDispatcherTest extends WebhookTestCase
{
    private function delivery(?ApiWebhook $webhook = null, array $attrs = []): ApiWebhookDelivery
    {
        return ApiWebhookDelivery::factory()->for($webhook ?? ApiWebhook::factory()->create(), 'webhook')->create($attrs);
    }

    public function test_signature_covers_timestamp_and_raw_body(): void
    {
        Http::fake(['*' => Http::response('ok')]);
        $webhook = ApiWebhook::factory()->create(['secret' => 'whsec_abc', 'headers' => ['X-Api-Token' => 'tok']]);
        $delivery = $this->delivery($webhook, ['payload' => ['event' => 'delivered', 'email' => 'é@exemple.dz']]);

        app(WebhookDispatcher::class)->attempt($delivery);

        Http::assertSent(function ($request) use ($delivery) {
            $ts = (int) $request->header('X-Mailer-Timestamp')[0];
            $expected = 'sha256='.hash_hmac('sha256', $ts.'.'.$request->body(), 'whsec_abc');

            return $request->header('X-Mailer-Signature')[0] === $expected
                && $request->header('X-Mailer-Delivery')[0] === $delivery->uuid
                && $request->header('X-Mailer-Event')[0] === 'delivered'
                && $request->header('X-Api-Token')[0] === 'tok'
                && $request->header('Content-Type')[0] === 'application/json'
                && json_decode($request->body(), true) === ['event' => 'delivered', 'email' => 'é@exemple.dz'];
        });
        $delivery->refresh();
        $this->assertSame('succeeded', $delivery->status);
        $this->assertSame(1, $delivery->attempts);
        $this->assertNotNull($delivery->delivered_at);
        $this->assertNotNull($delivery->duration_ms);
        $this->assertSame('succeeded', $delivery->webhook->last_delivery_status);
    }

    public function test_retry_schedule_follows_the_backoff_then_fails(): void
    {
        Queue::fake();
        Http::fake(['*' => Http::response(str_repeat('x', 3000), 503)]);
        Carbon::setTestNow('2026-10-20 10:00:00');
        $delivery = $this->delivery();
        $dispatcher = app(WebhookDispatcher::class);

        $expected = [60, 300, 1800, 7200, 43200];
        foreach ($expected as $i => $seconds) {
            $dispatcher->attempt($delivery->fresh());
            $delivery->refresh();
            $this->assertSame('retrying', $delivery->status);
            $this->assertSame($i + 1, $delivery->attempts);
            $this->assertSame(503, $delivery->response_status);
            $this->assertSame(1000, strlen($delivery->response_body_excerpt));
            $this->assertTrue($delivery->next_retry_at->equalTo(now()->addSeconds($seconds)), "attempt $i");
        }
        Queue::assertPushed(DeliverWebhookJob::class, 5);

        $dispatcher->attempt($delivery->fresh());
        $delivery->refresh();
        $this->assertSame('failed', $delivery->status);
        $this->assertSame(6, $delivery->attempts);
        $this->assertNull($delivery->next_retry_at);
        $this->assertSame(1, $delivery->webhook->failure_count);
        $this->assertSame('failed', $delivery->webhook->last_delivery_status);
        Queue::assertPushed(DeliverWebhookJob::class, 5);
        Carbon::setTestNow();
    }

    public function test_job_retries_with_http_sequence_until_success(): void
    {
        Queue::fake();
        Http::fake(['*' => Http::sequence()->push('', 500)->push('', 502)->push('ok', 200)]);
        $delivery = $this->delivery(attrs: ['status' => 'pending']);

        for ($i = 0; $i < 3; $i++) {
            $delivery->refresh();
            $delivery->update(['next_retry_at' => null]); // as if the delayed job came due
            (new DeliverWebhookJob($delivery->id))->handle(app(WebhookDispatcher::class));
        }

        $delivery->refresh();
        $this->assertSame('succeeded', $delivery->status);
        $this->assertSame(3, $delivery->attempts);
        $this->assertSame(0, $delivery->webhook->failure_count);
        Queue::assertPushed(DeliverWebhookJob::class, 2);

        // A finished delivery is never sent again.
        (new DeliverWebhookJob($delivery->id))->handle(app(WebhookDispatcher::class));
        Http::assertSentCount(3);
    }

    public function test_delayed_job_does_not_run_before_its_retry_time(): void
    {
        Http::fake();
        $delivery = $this->delivery(attrs: ['status' => 'retrying', 'attempts' => 1, 'next_retry_at' => now()->addMinutes(5)]);

        (new DeliverWebhookJob($delivery->id))->handle(app(WebhookDispatcher::class));

        Http::assertNothingSent();
    }

    public function test_network_errors_count_as_failures(): void
    {
        Queue::fake();
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('timeout'));
        $delivery = $this->delivery();

        app(WebhookDispatcher::class)->attempt($delivery);

        $delivery->refresh();
        $this->assertSame('retrying', $delivery->status);
        $this->assertNull($delivery->response_status);
        $this->assertStringContainsString('timeout', $delivery->response_body_excerpt);
    }

    public function test_private_host_is_refused_before_each_delivery(): void
    {
        Queue::fake();
        Http::fake();
        $webhook = ApiWebhook::factory()->create(['url' => 'https://intranet.exemple.dz/hook']);
        $delivery = $this->delivery($webhook);

        app(WebhookDispatcher::class)->attempt($delivery);

        Http::assertNothingSent();
        $this->assertSame('retrying', $delivery->fresh()->status);
        $this->assertStringContainsString('non public', $delivery->fresh()->response_body_excerpt);
    }

    public function test_auto_disables_after_fifty_consecutive_failures_and_notifies_admins(): void
    {
        Queue::fake();
        Http::fake(['*' => Http::response('', 500)]);
        $admin = $this->admin();
        $webhook = ApiWebhook::factory()->create(['failure_count' => 49]);
        $delivery = $this->delivery($webhook, ['status' => 'retrying', 'attempts' => 5]);

        app(WebhookDispatcher::class)->attempt($delivery);

        $webhook->refresh();
        $this->assertSame(50, $webhook->failure_count);
        $this->assertFalse($webhook->is_active);
        $this->assertSame(1, $admin->notifications()->count());
        $this->assertSame('delivery_infrastructure', $admin->notifications()->first()->data['category']);

        // Deliveries of an inactive webhook are not sent.
        Http::fake();
        $next = $this->delivery($webhook);
        (new DeliverWebhookJob($next->id))->handle(app(WebhookDispatcher::class));
        Http::assertNothingSent();
        $this->assertSame('failed', $next->fresh()->status);

        // Re-enabling resets the counter.
        $this->actingAs($admin)->patchJson("/api/v1/webhooks/{$webhook->uuid}", ['is_active' => true])->assertOk();
        $this->assertSame(0, $webhook->fresh()->failure_count);
    }

    public function test_success_resets_the_consecutive_failure_counter(): void
    {
        Http::fake(['*' => Http::response('ok')]);
        $webhook = ApiWebhook::factory()->create(['failure_count' => 12]);

        app(WebhookDispatcher::class)->attempt($this->delivery($webhook));

        $this->assertSame(0, $webhook->fresh()->failure_count);
    }

    public function test_sweep_requeues_overdue_retries_and_prunes_old_deliveries(): void
    {
        Queue::fake();
        $overdue = $this->delivery(attrs: ['status' => 'retrying', 'next_retry_at' => now()->subMinutes(10)]);
        $this->delivery(attrs: ['status' => 'retrying', 'next_retry_at' => now()->addMinutes(10)]);
        $old = $this->delivery(attrs: ['status' => 'succeeded']);
        $old->forceFill(['created_at' => now()->subDays(31)])->save();

        (new SweepWebhookDeliveriesJob)->handle();

        Queue::assertPushed(DeliverWebhookJob::class, 1);
        Queue::assertPushed(DeliverWebhookJob::class, fn ($job) => $job->deliveryId === $overdue->id);
        $this->assertNull(ApiWebhookDelivery::query()->find($old->id));
    }
}
