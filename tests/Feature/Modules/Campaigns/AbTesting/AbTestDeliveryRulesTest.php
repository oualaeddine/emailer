<?php

namespace Tests\Feature\Modules\Campaigns\AbTesting;

use App\Modules\Campaigns\AbTesting\Jobs\PickAbWinnerJob;
use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Jobs\DispatchCampaignJob;
use App\Modules\Campaigns\Models\AbTestVariant;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\DeliveryEngine\Jobs\SendEmailJob;
use App\Modules\Tracking\Models\Message;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\Feature\Modules\Campaigns\AbTesting\Concerns\BuildsAbTests;
use Tests\Feature\Modules\Campaigns\Concerns\BuildsCampaignFixtures;
use Tests\TestCase;

/**
 * docs/45 §45.7.2 — A/B delivery rules: no best-time / batches scheduling,
 * "send to new contacts" of a decided test sends the winner only, and each
 * version's preview text is sent as a hidden preheader.
 */
class AbTestDeliveryRulesTest extends TestCase
{
    use BuildsAbTests, BuildsCampaignFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function variant(Campaign $campaign, string $label): AbTestVariant
    {
        return AbTestVariant::query()->where('campaign_id', $campaign->id)->where('label', $label)->firstOrFail();
    }

    // ---- scheduling modes --------------------------------------------------

    public function test_best_time_is_rejected_for_an_ab_test(): void
    {
        Bus::fake();
        $owner = $this->manager();
        $campaign = $this->abCampaign($owner, 4);

        $this->actingAs($owner)->postJson("/api/v1/campaigns/{$campaign->uuid}/schedule", [
            'mode' => 'best_time',
            'scheduled_at' => now()->addDay()->toIso8601String(),
        ])->assertStatus(422)->assertJsonValidationErrors(['mode']);

        $campaign->refresh();
        $this->assertSame(CampaignStatus::Draft->value, $campaign->status);
        $this->assertFalse($campaign->send_time_optimized);
        Bus::assertNotDispatched(DispatchCampaignJob::class);
    }

    public function test_batches_are_rejected_for_an_ab_test(): void
    {
        Bus::fake();
        $owner = $this->manager();
        $campaign = $this->abCampaign($owner, 4);

        $this->actingAs($owner)->postJson("/api/v1/campaigns/{$campaign->uuid}/schedule", [
            'mode' => 'batches',
            'batch_size' => 100,
            'batch_interval_minutes' => 30,
        ])->assertStatus(422)->assertJsonValidationErrors(['mode']);

        $campaign->refresh();
        $this->assertSame(CampaignStatus::Draft->value, $campaign->status);
        $this->assertNull($campaign->batch_size);
        Bus::assertNotDispatched(DispatchCampaignJob::class);
    }

    public function test_classic_campaign_can_still_use_best_time_and_batches(): void
    {
        Bus::fake();
        $owner = $this->manager();
        $best = $this->campaignWithAudience($owner, ['lists' => [$this->listWith($this->recipients(2))->id]]);
        $batches = $this->campaignWithAudience($owner, ['lists' => [$this->listWith($this->recipients(2))->id]]);

        $this->actingAs($owner)->postJson("/api/v1/campaigns/{$best->uuid}/schedule", ['mode' => 'best_time'])->assertOk();
        $this->actingAs($owner)->postJson("/api/v1/campaigns/{$batches->uuid}/schedule", [
            'mode' => 'batches', 'batch_size' => 100, 'batch_interval_minutes' => 30,
        ])->assertOk();
    }

    // ---- send to new contacts ----------------------------------------------

    public function test_new_contacts_child_of_a_decided_test_sends_the_winner_as_classic(): void
    {
        Bus::fake();
        $owner = $this->manager();
        $campaign = $this->abCampaign($owner, 4, [], [
            ['label' => 'A', 'subject' => 'Objet A', 'preview_text' => 'Aperçu A'],
            ['label' => 'B', 'subject' => 'Objet B', 'preview_text' => 'Aperçu B'],
        ]);
        $b = $this->variant($campaign, 'B');
        $campaign->update(['status' => CampaignStatus::Completed->value, 'winner_variant_id' => $b->id]);

        $response = $this->actingAs($owner)->postJson("/api/v1/campaigns/{$campaign->uuid}/send-to-new-contacts")
            ->assertCreated()
            ->assertJsonPath('data.type', 'classic')
            ->assertJsonPath('data.subject', 'Objet B')
            ->assertJsonPath('data.preview_text', 'Aperçu B')
            ->assertJsonPath('data.ab_test', null);

        $child = Campaign::query()->where('uuid', $response->json('data.id'))->firstOrFail();
        $this->assertFalse($child->ab_test_enabled);
        $this->assertSame((string) $campaign->html_body, (string) $child->html_body);
        $this->assertSame(0, $child->abTestVariants()->count());
    }

    public function test_new_contacts_child_of_a_content_test_takes_the_winner_html(): void
    {
        Bus::fake();
        $owner = $this->manager();
        $campaign = $this->abCampaign($owner, 4, ['type' => 'content'], [
            ['label' => 'A', 'subject' => 'Objet commun', 'html_body' => '<p>Version A</p>'],
            ['label' => 'B', 'subject' => 'Objet commun', 'html_body' => '<p>Version B</p>'],
        ]);
        $campaign->update(['status' => CampaignStatus::Completed->value, 'winner_variant_id' => $this->variant($campaign, 'B')->id]);

        $response = $this->actingAs($owner)->postJson("/api/v1/campaigns/{$campaign->uuid}/send-to-new-contacts")
            ->assertCreated()
            ->assertJsonPath('data.type', 'classic');

        $child = Campaign::query()->where('uuid', $response->json('data.id'))->firstOrFail();
        $this->assertSame('<p>Version B</p>', $child->html_body);
    }

    public function test_new_contacts_child_without_a_winner_keeps_the_test(): void
    {
        Bus::fake();
        $owner = $this->manager();
        $campaign = $this->abCampaign($owner, 4);
        $campaign->update(['status' => CampaignStatus::Completed->value]);

        $response = $this->actingAs($owner)->postJson("/api/v1/campaigns/{$campaign->uuid}/send-to-new-contacts")
            ->assertCreated()
            ->assertJsonPath('data.type', 'ab_test')
            ->assertJsonPath('data.ab_test.variants.1.subject', 'Objet B');

        $child = Campaign::query()->where('uuid', $response->json('data.id'))->firstOrFail();
        $this->assertTrue($child->ab_test_enabled);
        $this->assertSame(2, $child->abTestVariants()->count());
    }

    // ---- preview text ------------------------------------------------------

    public function test_each_version_is_sent_with_its_preview_text(): void
    {
        Bus::fake([SendEmailJob::class, PickAbWinnerJob::class, DispatchCampaignJob::class]);
        $campaign = $this->abCampaign($this->manager(), 20, ['sample_percent' => 50], [
            ['label' => 'A', 'subject' => 'Objet A', 'preview_text' => 'Aperçu de la version A'],
            ['label' => 'B', 'subject' => 'Objet B'],
        ]);
        $campaign->update(['status' => CampaignStatus::Running->value]);
        app()->call([new DispatchCampaignJob($campaign->id), 'handle']);

        $a = Message::query()->where('campaign_id', $campaign->id)->where('variant_id', $this->variant($campaign, 'A')->id)->get();
        $b = Message::query()->where('campaign_id', $campaign->id)->where('variant_id', $this->variant($campaign, 'B')->id)->get();

        $this->assertNotEmpty($a);
        $this->assertNotEmpty($b);
        $this->assertTrue($a->every(fn (Message $m): bool => str_contains((string) $m->html_body, 'display:none')
            && str_contains((string) $m->html_body, 'Aperçu de la version A')));
        $this->assertTrue($b->every(fn (Message $m): bool => ! str_contains((string) $m->html_body, 'Aperçu de la version A')));
    }
}
