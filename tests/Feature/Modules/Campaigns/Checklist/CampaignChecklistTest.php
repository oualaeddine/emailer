<?php

namespace Tests\Feature\Modules\Campaigns\Checklist;

use App\Domain\Enums\RecipientListType;
use App\Domain\Enums\RoleName;
use App\Modules\Campaigns\Jobs\CheckCampaignLinksJob;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Services\Checklist\CampaignChecklistContract;
use App\Modules\Campaigns\Services\Checklist\CampaignChecklistService;
use App\Modules\Campaigns\Services\Checklist\LinkChecker;
use App\Modules\Identity\Models\User;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Recipients\Models\RecipientList;
use App\Modules\Senders\Models\Sender;
use App\Modules\Templates\Services\HostResolver;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * docs/45-brevo-api-contracts.md §45.4.5 — WP-45 pre-send checklist.
 */
class CampaignChecklistTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Cache::flush();
        $this->app->instance(HostResolver::class, new class extends HostResolver
        {
            public function resolve(string $host): array
            {
                return match (true) {
                    str_starts_with($host, 'internal') => ['10.0.0.5'],
                    $host === 'metadata.test' => ['169.254.169.254'],
                    default => ['93.184.216.34'],
                };
            }
        });
    }

    private function campaign(array $attrs = []): Campaign
    {
        return Campaign::query()->create(array_merge([
            'name' => 'Test',
            'subject' => 'Bonjour',
            'html_body' => '<p>Hello</p><a href="https://example.com/a">a</a><a href="{{ unsubscribe }}">u</a>',
        ], $attrs));
    }

    private function listWith(int $n): RecipientList
    {
        $list = RecipientList::query()->create(['name' => 'L', 'type' => RecipientListType::Static->value]);
        $r = Recipient::factory()->count($n)->create();
        $list->recipients()->syncWithoutDetaching($r->mapWithKeys(fn ($x) => [$x->id => ['added_at' => now()]])->all());

        return $list;
    }

    private function itemStatus(Campaign $c, string $key): string
    {
        return app(CampaignChecklistContract::class)->evaluate($c)->item($key)->status;
    }

    public function test_contract_is_bound_to_service(): void
    {
        $this->assertInstanceOf(CampaignChecklistService::class, app(CampaignChecklistContract::class));
        $this->assertTrue(app()->bound(CampaignChecklistService::class));
    }

    public function test_sender_verified_states(): void
    {
        $c = $this->campaign();
        $this->assertSame('error', $this->itemStatus($c, 'sender_verified'));

        $unverified = Sender::factory()->create()->id;
        $c->setAttribute('sender_id', $unverified);
        $this->assertSame('error', $this->itemStatus($c, 'sender_verified'));

        $verified = Sender::factory()->verified()->create()->id;
        $c->setAttribute('sender_id', $verified);
        $this->assertSame('ok', $this->itemStatus($c, 'sender_verified'));
    }

    public function test_recipients_selected_states(): void
    {
        $c = $this->campaign();
        $this->assertSame('error', $this->itemStatus($c, 'recipients_selected'));

        $c->recipient_list_id = $this->listWith(2)->id;
        $c->save();
        $item = app(CampaignChecklistContract::class)->evaluate($c)->item('recipients_selected');
        $this->assertSame('ok', $item->status);
        $this->assertSame(['count' => 2], $item->details);
    }

    public function test_subject_and_design_states(): void
    {
        $this->assertSame('ok', $this->itemStatus($this->campaign(), 'subject_filled'));
        $empty = $this->campaign(['subject' => '  ', 'html_body' => null]);
        $this->assertSame('error', $this->itemStatus($empty, 'subject_filled'));
        $this->assertSame('error', $this->itemStatus($empty, 'design_ready'));
        $this->assertSame('ok', $this->itemStatus($this->campaign(), 'design_ready'));
    }

    public function test_unsubscribe_link_states(): void
    {
        $this->assertSame('ok', $this->itemStatus($this->campaign(), 'unsubscribe_link'));
        $href = $this->campaign(['html_body' => '<a href="https://x.test/unsubscribe/abc">x</a>']);
        $this->assertSame('ok', $this->itemStatus($href, 'unsubscribe_link'));
        $none = $this->campaign(['html_body' => '<p>Hi</p>']);
        $item = app(CampaignChecklistContract::class)->evaluate($none)->item('unsubscribe_link');
        $this->assertSame('error', $item->status);
        $this->assertTrue($item->blocking);
    }

    public function test_test_sent_is_a_warning_not_an_error(): void
    {
        $c = $this->campaign();
        $item = app(CampaignChecklistContract::class)->evaluate($c)->item('test_sent');
        $this->assertSame('warning', $item->status);
        $this->assertFalse($item->blocking);

        $c->setAttribute('test_sent_at', now());
        $this->assertSame('ok', $this->itemStatus($c, 'test_sent'));
    }

    public function test_email_size_states(): void
    {
        $this->assertSame('ok', $this->itemStatus($this->campaign(), 'email_size'));
        $big = $this->campaign(['html_body' => str_repeat('a', 102401)]);
        $item = app(CampaignChecklistContract::class)->evaluate($big)->item('email_size');
        $this->assertSame('warning', $item->status);
        $this->assertSame(['bytes' => 102401, 'limit' => 102400], $item->details);
    }

    public function test_spam_score_is_skipped_and_links_pending_until_job_ran(): void
    {
        $c = $this->campaign();
        $this->assertSame('skipped', $this->itemStatus($c, 'spam_score'));
        $this->assertSame('pending', $this->itemStatus($c, 'links_valid'));
    }

    public function test_blocking_failures_and_overall_status(): void
    {
        $c = $this->campaign(['html_body' => '<p>no unsub</p>']);
        $service = app(CampaignChecklistContract::class);
        $failures = $service->blockingFailures($c);
        $this->assertContains('unsubscribe_link', $failures);
        $this->assertContains('sender_verified', $failures);
        $this->assertNotContains('test_sent', $failures);
        $this->assertSame('blocking', $service->evaluate($c)->toArray()['status']);
        $this->assertFalse($service->evaluate($c)->toArray()['can_schedule']);
    }

    public function test_complete_when_everything_ok(): void
    {
        $c = $this->campaign(['recipient_list_id' => $this->listWith(1)->id]);
        $c->setAttribute('sender_id', Sender::factory()->verified()->create()->id);
        $c->setAttribute('test_sent_at', now());
        Cache::put(LinkChecker::cacheKey($c->uuid, $c->html_body), ['checked' => 1, 'broken' => []]);

        $data = app(CampaignChecklistContract::class)->evaluate($c)->toArray();
        $this->assertTrue($data['can_schedule']);
        $this->assertSame([], app(CampaignChecklistContract::class)->blockingFailures($c));
        // spam_score is skipped, which does not make the checklist incomplete.
        $this->assertSame('complete', $data['status']);
    }

    public function test_link_job_detects_broken_links_with_head_then_get_fallback(): void
    {
        Http::fake([
            'ok.test/*' => Http::response('', 200),
            'nohead.test/*' => Http::sequence()->push('', 405)->push('', 200),
            'gone.test/*' => Http::response('', 404),
        ]);
        $c = $this->campaign(['html_body' => '<a href="https://ok.test/a">1</a><a href="https://nohead.test/b">2</a>'
            .'<a href="https://gone.test/c">3</a><a href="mailto:a@b.c">m</a><a href="{{ unsubscribe }}">u</a>']);

        (new CheckCampaignLinksJob($c->id))->handle(app(LinkChecker::class));

        $item = app(CampaignChecklistContract::class)->evaluate($c)->item('links_valid');
        $this->assertSame('warning', $item->status);
        $this->assertSame([['url' => 'https://gone.test/c', 'status' => 404]], $item->details['broken']);
        Http::assertSentCount(5); // ok HEAD, nohead HEAD+GET, gone HEAD+GET
    }

    public function test_link_job_ok_and_url_cap(): void
    {
        Http::fake(['*' => Http::response('', 200)]);
        $links = '';
        for ($i = 0; $i < 120; $i++) {
            $links .= "<a href=\"https://site{$i}.test/\">x</a>";
        }
        $c = $this->campaign(['html_body' => $links]);

        (new CheckCampaignLinksJob($c->id))->handle(app(LinkChecker::class));

        $this->assertSame('ok', $this->itemStatus($c, 'links_valid'));
        Http::assertSentCount(100);
    }

    public function test_link_cache_is_per_content_hash(): void
    {
        Http::fake(['*' => Http::response('', 200)]);
        $c = $this->campaign();
        (new CheckCampaignLinksJob($c->id))->handle(app(LinkChecker::class));
        $this->assertSame('ok', $this->itemStatus($c, 'links_valid'));

        $c->html_body .= '<p>changed</p>';
        $this->assertSame('pending', $this->itemStatus($c, 'links_valid'));
    }

    public function test_get_checklist_endpoint_and_permissions(): void
    {
        $c = $this->campaign();
        $viewer = User::factory()->withRole(RoleName::Viewer)->create();

        $this->actingAs($viewer)->getJson("/api/v1/campaigns/{$c->uuid}/checklist")
            ->assertOk()
            ->assertJsonPath('data.campaign_id', $c->uuid)
            ->assertJsonPath('data.status', 'blocking')
            ->assertJsonCount(10, 'data.items')
            ->assertJsonPath('data.items.0.key', 'sender_verified');

        $this->app['auth']->forgetGuards();
        $this->getJson("/api/v1/campaigns/{$c->uuid}/checklist")->assertUnauthorized();
    }

    public function test_post_links_dispatches_job_and_returns_pending(): void
    {
        Queue::fake();
        $c = $this->campaign();
        Cache::put(LinkChecker::cacheKey($c->uuid, $c->html_body), ['checked' => 1, 'broken' => []]);
        $operator = User::factory()->withRole(RoleName::MarketingOperator)->create();

        $this->actingAs($operator)->postJson("/api/v1/campaigns/{$c->uuid}/checklist/links")
            ->assertStatus(202)
            ->assertJsonPath('data.items.7.key', 'links_valid')
            ->assertJsonPath('data.items.7.status', 'pending');

        Queue::assertPushed(CheckCampaignLinksJob::class, fn ($j) => $j->campaignId === $c->id);
    }

    public function test_post_links_forbidden_without_create_permission(): void
    {
        Queue::fake();
        $c = $this->campaign();
        $viewer = User::factory()->withRole(RoleName::Viewer)->create();

        $this->actingAs($viewer)->postJson("/api/v1/campaigns/{$c->uuid}/checklist/links")->assertForbidden();
        Queue::assertNothingPushed();
    }

    public function test_link_checker_blocks_non_public_hosts_without_sending(): void
    {
        Http::fake(['*' => Http::response('', 200)]);

        $result = app(LinkChecker::class)->check('<a href="http://internal.test/admin">x</a><a href="http://metadata.test/latest">m</a>');

        $this->assertSame(2, $result['checked']);
        $this->assertSame([
            ['url' => 'http://internal.test/admin', 'status' => 0, 'reason' => 'blocked'],
            ['url' => 'http://metadata.test/latest', 'status' => 0, 'reason' => 'blocked'],
        ], $result['broken']);
        Http::assertNothingSent();
        $this->assertSame(0, app(LinkChecker::class)->probe('http://internal.test/'));
    }

    public function test_link_checker_does_not_follow_redirects_and_counts_3xx_ok(): void
    {
        Http::fake(['*' => Http::response('', 302, ['Location' => 'http://internal.test/secret'])]);

        $result = app(LinkChecker::class)->check('<a href="https://short.test/x">x</a>');

        $this->assertSame([], $result['broken']);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->url() === 'https://short.test/x');
    }

    public function test_probe_many_returns_statuses_in_parallel_batches(): void
    {
        Http::fake([
            'gone.test/*' => Http::response('', 404),
            'blocked.test/*' => Http::response('', 200),
            '*' => Http::response('', 200),
        ]);
        $urls = [];
        for ($i = 0; $i < 25; $i++) {
            $urls[] = "https://site{$i}.test/";
        }
        $urls[] = 'https://gone.test/a';
        $urls[] = 'http://internal.test/a';

        $result = app(LinkChecker::class)->probeMany($urls);

        $this->assertCount(27, $result);
        $this->assertSame(200, $result['https://site24.test/']);
        $this->assertSame(404, $result['https://gone.test/a']);
        $this->assertSame(0, $result['http://internal.test/a']);
        $this->assertSame([], app(LinkChecker::class)->probeMany([]));
    }
}
