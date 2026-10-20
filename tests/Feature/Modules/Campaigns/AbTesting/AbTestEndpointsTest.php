<?php

namespace Tests\Feature\Modules\Campaigns\AbTesting;

use App\Domain\Enums\RoleName;
use App\Modules\Campaigns\AbTesting\Services\AbTestDispatcher;
use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Jobs\DispatchCampaignJob;
use App\Modules\Campaigns\Models\AbTestVariant;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Identity\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\Feature\Modules\Campaigns\AbTesting\Concerns\BuildsAbTests;
use Tests\Feature\Modules\Campaigns\Concerns\BuildsCampaignFixtures;
use Tests\TestCase;

/**
 * docs/45-brevo-api-contracts.md §45.7.2 — `PUT /campaigns/{id}/ab-test`,
 * `POST /campaigns/{id}/ab-test/winner`, `CampaignV2Detail.ab_test`, the
 * schedule guard (`errors.ab_test`) and duplicate.
 */
class AbTestEndpointsTest extends TestCase
{
    use BuildsAbTests, BuildsCampaignFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function viewer(): User
    {
        return User::factory()->withRole(RoleName::Viewer)->create();
    }

    // ---- PUT ab-test -------------------------------------------------------

    public function test_enabling_stores_config_and_variants_and_switches_type(): void
    {
        $owner = $this->operator();
        $campaign = $this->campaignWithAudience($owner, ['lists' => [$this->listWith($this->recipients(2))->id]]);

        $response = $this->actingAs($owner)->putJson("/api/v1/campaigns/{$campaign->uuid}/ab-test", [
            'enabled' => true,
            'config' => $this->abConfig(['sample_percent' => 30, 'winner_metric' => 'clicks', 'test_duration_minutes' => 120]),
            'variants' => $this->abVariants('Promo -20 %', 'Dernier jour !'),
        ])->assertOk();

        $response->assertJsonPath('data.type', 'ab_test')
            ->assertJsonPath('data.ab_test_enabled', true)
            ->assertJsonPath('data.subject', 'Promo -20 %')
            ->assertJsonPath('data.ab_test.config', [
                'type' => 'subject', 'sample_percent' => 30, 'winner_metric' => 'clicks',
                'test_duration_minutes' => 120, 'auto_send_winner' => true,
            ])
            ->assertJsonPath('data.ab_test.phase', 'not_started')
            ->assertJsonPath('data.ab_test.winner_variant_id', null)
            ->assertJsonPath('data.ab_test.variants.0.label', 'A')
            ->assertJsonPath('data.ab_test.variants.0.preview_text', 'Aperçu A')
            ->assertJsonPath('data.ab_test.variants.1.subject', 'Dernier jour !')
            ->assertJsonPath('data.ab_test.variants.1.is_winner', false);

        $this->assertSame(2, AbTestVariant::query()->where('campaign_id', $campaign->id)->count());
        $this->assertIsString($response->json('data.ab_test.variants.0.id'));
    }

    public function test_config_validation(): void
    {
        $owner = $this->operator();
        $campaign = $this->campaignWithAudience($owner, ['lists' => [$this->listWith($this->recipients(1))->id]]);
        $url = "/api/v1/campaigns/{$campaign->uuid}/ab-test";

        $cases = [
            'config.sample_percent' => ['sample_percent' => 4],
            'config.type' => ['type' => 'sender'],
            'config.winner_metric' => ['winner_metric' => 'revenue'],
            'config.test_duration_minutes' => ['test_duration_minutes' => 30],
        ];
        foreach ($cases as $key => $override) {
            $this->actingAs($owner)->putJson($url, ['enabled' => true, 'config' => $this->abConfig($override), 'variants' => $this->abVariants()])
                ->assertStatus(422)->assertJsonValidationErrors([$key]);
        }

        $this->actingAs($owner)->putJson($url, ['enabled' => true, 'config' => $this->abConfig(['sample_percent' => 51]), 'variants' => $this->abVariants()])
            ->assertJsonValidationErrors(['config.sample_percent']);
        $this->actingAs($owner)->putJson($url, ['enabled' => true, 'config' => $this->abConfig(['test_duration_minutes' => 10081]), 'variants' => $this->abVariants()])
            ->assertJsonValidationErrors(['config.test_duration_minutes']);

        // Subject test: both subjects required; labels A + B, distinct.
        $this->actingAs($owner)->putJson($url, ['enabled' => true, 'config' => $this->abConfig(), 'variants' => [['label' => 'A', 'subject' => 'x'], ['label' => 'B']]])
            ->assertJsonValidationErrors(['variants.1.subject']);
        $this->actingAs($owner)->putJson($url, ['enabled' => true, 'config' => $this->abConfig(), 'variants' => [['label' => 'A', 'subject' => 'x'], ['label' => 'A', 'subject' => 'y']]])
            ->assertJsonValidationErrors(['variants.1.label']);
        $this->actingAs($owner)->putJson($url, ['enabled' => true, 'config' => $this->abConfig(), 'variants' => [['label' => 'A', 'subject' => 'x']]])
            ->assertJsonValidationErrors(['variants']);

        // Boundaries are accepted.
        $this->actingAs($owner)->putJson($url, ['enabled' => true, 'config' => $this->abConfig(['sample_percent' => 5, 'test_duration_minutes' => 60]), 'variants' => $this->abVariants()])
            ->assertOk();
        $this->actingAs($owner)->putJson($url, ['enabled' => true, 'config' => $this->abConfig(['sample_percent' => 50, 'test_duration_minutes' => 10080]), 'variants' => $this->abVariants()])
            ->assertOk();
    }

    public function test_content_test_keeps_variant_html_and_disable_reverts_to_classic(): void
    {
        $owner = $this->operator();
        $campaign = $this->campaignWithAudience($owner, ['lists' => [$this->listWith($this->recipients(1))->id]]);
        $url = "/api/v1/campaigns/{$campaign->uuid}/ab-test";

        $this->actingAs($owner)->putJson($url, [
            'enabled' => true,
            'config' => $this->abConfig(['type' => 'content']),
            'variants' => [['label' => 'A', 'subject' => ''], ['label' => 'B', 'subject' => '', 'html_body' => '<p>Version B</p>']],
        ])->assertOk()
            ->assertJsonPath('data.subject', 'Objet') // content test: campaign subject untouched
            ->assertJsonPath('data.ab_test.variants.0.html_body', null)
            ->assertJsonPath('data.ab_test.variants.1.html_body', '<p>Version B</p>');

        $this->actingAs($owner)->putJson($url, ['enabled' => false])->assertOk()
            ->assertJsonPath('data.type', 'classic')
            ->assertJsonPath('data.ab_test_enabled', false)
            ->assertJsonPath('data.ab_test', null);
    }

    public function test_put_is_409_unless_draft_and_403_without_permission(): void
    {
        $owner = $this->operator();
        $campaign = $this->campaignWithAudience($owner, ['lists' => [$this->listWith($this->recipients(1))->id]]);
        $payload = ['enabled' => true, 'config' => $this->abConfig(), 'variants' => $this->abVariants()];

        $this->actingAs($this->viewer())->putJson("/api/v1/campaigns/{$campaign->uuid}/ab-test", $payload)->assertForbidden();

        $campaign->update(['status' => CampaignStatus::Scheduled->value]);
        $this->actingAs($owner)->putJson("/api/v1/campaigns/{$campaign->uuid}/ab-test", $payload)->assertStatus(409);
    }

    // ---- schedule guard ----------------------------------------------------

    public function test_schedule_rejects_an_incomplete_ab_test_with_errors_ab_test(): void
    {
        Bus::fake();
        $owner = $this->manager();

        $same = $this->abCampaign($owner, 2, [], $this->abVariants('Pareil', 'Pareil'));
        $this->actingAs($owner)->postJson("/api/v1/campaigns/{$same->uuid}/schedule", ['mode' => 'now'])
            ->assertStatus(422)->assertJsonValidationErrors(['ab_test']);

        $content = $this->abCampaign($owner, 2, ['type' => 'content'], [['label' => 'A'], ['label' => 'B']]);
        $this->actingAs($owner)->postJson("/api/v1/campaigns/{$content->uuid}/schedule", ['mode' => 'now'])
            ->assertStatus(422)->assertJsonValidationErrors(['ab_test']);

        $notConfigured = $this->campaignWithAudience($owner, ['lists' => [$this->listWith($this->recipients(1))->id]], ['type' => 'ab_test']);
        $this->actingAs($owner)->postJson("/api/v1/campaigns/{$notConfigured->uuid}/schedule", ['mode' => 'now'])
            ->assertStatus(422)->assertJsonValidationErrors(['ab_test']);

        // A broken config stored outside the endpoint is caught too.
        $broken = $this->abCampaign($owner, 2);
        $broken->update(['ab_test_config' => ['type' => 'subject', 'sample_percent' => 90, 'winner_metric' => 'opens', 'test_duration_minutes' => 240]]);
        $this->actingAs($owner)->postJson("/api/v1/campaigns/{$broken->uuid}/schedule", ['mode' => 'now'])
            ->assertStatus(422)->assertJsonValidationErrors(['ab_test']);

        $ok = $this->abCampaign($owner, 2);
        $this->actingAs($owner)->postJson("/api/v1/campaigns/{$ok->uuid}/schedule", ['mode' => 'now'])->assertOk()
            ->assertJsonPath('data.status', 'running');
    }

    // ---- winner endpoint ---------------------------------------------------

    public function test_manual_pick_requires_the_test_phase_and_releases_the_remainder(): void
    {
        Bus::fake();
        $owner = $this->manager();
        $campaign = $this->abCampaign($owner, 10, ['auto_send_winner' => false]);
        $b = AbTestVariant::query()->where('campaign_id', $campaign->id)->where('label', 'B')->firstOrFail();
        $url = "/api/v1/campaigns/{$campaign->uuid}/ab-test/winner";

        // Draft: no test running.
        $this->actingAs($owner)->postJson($url, ['variant_id' => $b->uuid])->assertStatus(409);

        $campaign->update(['status' => CampaignStatus::Running->value]);
        app(AbTestDispatcher::class)->runDispatchCycle($campaign);

        $this->actingAs($owner)->getJson("/api/v1/campaigns/{$campaign->uuid}")->assertJsonPath('data.ab_test.phase', 'testing');

        $this->actingAs($this->viewer())->postJson($url, ['variant_id' => $b->uuid])->assertForbidden();
        $this->actingAs($owner)->postJson($url, ['variant_id' => $this->abCampaign($owner, 1)->abTestVariants()->first()->uuid])
            ->assertStatus(422)->assertJsonValidationErrors(['variant_id']);
        $this->actingAs($owner)->postJson($url, ['variant_id' => 'not-a-uuid'])->assertJsonValidationErrors(['variant_id']);

        $this->actingAs($owner)->postJson($url, ['variant_id' => $b->uuid])->assertOk()
            ->assertJsonPath('data.ab_test.winner_variant_id', $b->uuid)
            ->assertJsonPath('data.ab_test.phase', 'winner_picked')
            ->assertJsonPath('data.ab_test.variants.1.is_winner', true);

        $this->assertDatabaseHas('audit_logs', ['action' => 'campaign.ab_winner_picked', 'auditable_id' => $campaign->id, 'user_id' => $owner->id]);
        Bus::assertDispatched(DispatchCampaignJob::class);

        // Second pick: conflict.
        $this->actingAs($owner)->postJson($url, ['variant_id' => $b->uuid])->assertStatus(409);
    }

    public function test_operator_can_only_pick_on_own_campaigns(): void
    {
        Bus::fake();
        $campaign = $this->abCampaign($this->manager(), 4);
        $campaign->update(['status' => CampaignStatus::Running->value]);
        app(AbTestDispatcher::class)->runDispatchCycle($campaign);
        $a = $campaign->abTestVariants()->first();

        $this->actingAs($this->operator())->postJson("/api/v1/campaigns/{$campaign->uuid}/ab-test/winner", ['variant_id' => $a->uuid])
            ->assertForbidden();
    }

    // ---- duplicate ---------------------------------------------------------

    public function test_duplicate_copies_the_ab_test_without_winner(): void
    {
        $owner = $this->manager();
        $campaign = $this->abCampaign($owner, 2);
        $campaign->abTestVariants()->where('label', 'A')->update(['is_winner' => true, 'sample_size' => 3]);

        $response = $this->actingAs($owner)->postJson("/api/v1/campaigns/{$campaign->uuid}/duplicate")->assertCreated();
        $response->assertJsonPath('data.type', 'ab_test')
            ->assertJsonPath('data.ab_test.config.sample_percent', 20)
            ->assertJsonPath('data.ab_test.winner_variant_id', null)
            ->assertJsonPath('data.ab_test.variants.0.subject', 'Objet A')
            ->assertJsonPath('data.ab_test.variants.0.is_winner', false)
            ->assertJsonPath('data.ab_test.variants.1.subject', 'Objet B');

        $copy = Campaign::query()->where('uuid', $response->json('data.id'))->firstOrFail();
        $this->assertSame(2, $copy->abTestVariants()->count());
        $this->assertSame(0, (int) $copy->abTestVariants()->sum('sample_size'));
    }

    public function test_list_exposes_ab_badge_data(): void
    {
        $owner = $this->manager();
        $this->abCampaign($owner, 1);

        $this->actingAs($owner)->getJson('/api/v1/campaigns?type=ab_test')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'ab_test')
            ->assertJsonPath('data.0.ab_test_enabled', true);
    }
}
