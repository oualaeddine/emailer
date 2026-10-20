<?php

namespace Tests\Feature\Modules\Campaigns;

use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Enums\CampaignSuspendedReason;
use App\Modules\Campaigns\Events\CampaignStatusChanged;
use App\Modules\Campaigns\Exceptions\InvalidCampaignTransitionException;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Services\CampaignService;
use App\Modules\Tracking\Models\Message;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Modules\Campaigns\Concerns\BuildsCampaignFixtures;
use Tests\TestCase;

/**
 * docs/15 §15.1 + docs/43 §43.4.1 / docs/45 §45.4.2 — full transition table,
 * `brevo_status` mapping and `available_actions` table.
 */
class CampaignStateMachineTest extends TestCase
{
    use BuildsCampaignFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    /**
     * Expected transition table (from → allowed to).
     *
     * @return array<string, array{CampaignStatus, list<CampaignStatus>}>
     */
    public static function transitionTable(): array
    {
        return [
            'draft' => [CampaignStatus::Draft, [CampaignStatus::Scheduled, CampaignStatus::Running, CampaignStatus::Cancelled]],
            'scheduled' => [CampaignStatus::Scheduled, [CampaignStatus::Running, CampaignStatus::Draft, CampaignStatus::Cancelled, CampaignStatus::Suspended]],
            'running' => [CampaignStatus::Running, [CampaignStatus::Paused, CampaignStatus::Completed, CampaignStatus::Cancelled, CampaignStatus::Suspended]],
            'paused' => [CampaignStatus::Paused, [CampaignStatus::Running, CampaignStatus::Cancelled, CampaignStatus::Suspended]],
            'suspended' => [CampaignStatus::Suspended, [CampaignStatus::Running, CampaignStatus::Cancelled]],
            'completed' => [CampaignStatus::Completed, []],
            'cancelled' => [CampaignStatus::Cancelled, []],
        ];
    }

    /**
     * @param  list<CampaignStatus>  $allowed
     */
    #[DataProvider('transitionTable')]
    public function test_transition_table(CampaignStatus $from, array $allowed): void
    {
        foreach (CampaignStatus::cases() as $to) {
            $this->assertSame(
                in_array($to, $allowed, true),
                $from->canTransitionTo($to),
                "{$from->value} -> {$to->value}",
            );
        }
    }

    public function test_brevo_status_and_status_tab_mapping(): void
    {
        $expected = [
            'draft' => ['draft', 'draft'],
            'scheduled' => ['queued', 'scheduled'],
            'running' => ['in_process', 'running'],
            'paused' => ['suspended', 'running'],
            'suspended' => ['suspended', 'suspended'],
            'completed' => ['sent', 'sent'],
            'cancelled' => ['cancelled', null],
        ];

        foreach ($expected as $status => [$brevo, $tab]) {
            $enum = CampaignStatus::from($status);
            $this->assertSame($brevo, $enum->brevoStatus(false)->value, $status);
            $this->assertSame($tab, $enum->statusTab(), $status);
            $this->assertSame('archive', $enum->brevoStatus(true)->value);
        }
    }

    public function test_available_actions_table(): void
    {
        $expected = [
            'draft' => ['edit', 'test_send', 'schedule', 'send_now', 'delete'],
            'scheduled' => ['edit', 'test_send', 'unschedule', 'send_now', 'cancel'],
            'running' => ['report', 'pause', 'cancel'],
            'paused' => ['report', 'resume', 'cancel'],
            'suspended' => ['report', 'edit', 'resume', 'cancel', 'archive'],
            'completed' => ['report', 'send_to_new_contacts', 'archive'],
            'cancelled' => ['archive', 'delete'],
        ];

        foreach ($expected as $status => $actions) {
            $campaign = Campaign::factory()->create(['status' => $status]);
            $this->assertSame(
                [...$actions, 'duplicate', 'move'],
                app(CampaignService::class)->availableActions($campaign),
                $status,
            );
        }

        $archived = Campaign::factory()->archived()->create();
        $this->assertSame(['report', 'unarchive', 'delete', 'duplicate', 'move'], app(CampaignService::class)->availableActions($archived));
    }

    public function test_cancelled_campaign_with_sent_messages_offers_report(): void
    {
        $campaign = Campaign::factory()->create(['status' => 'cancelled']);
        Message::factory()->create([
            'campaign_id' => $campaign->id,
            'recipient_id' => $this->recipients(1)->first()->id,
        ]);

        $this->assertSame(['report', 'archive', 'delete', 'duplicate', 'move'], app(CampaignService::class)->availableActions($campaign));
    }

    public function test_every_transition_fires_campaign_status_changed(): void
    {
        Event::fake([CampaignStatusChanged::class]);
        $campaign = Campaign::factory()->create(['status' => 'running']);

        app(CampaignService::class)->suspend($campaign, CampaignSuspendedReason::QuotaExhausted);

        Event::assertDispatched(CampaignStatusChanged::class, fn (CampaignStatusChanged $e) => $e->campaignId === $campaign->id
            && $e->from === 'running' && $e->to === 'suspended' && $e->reason === 'quota_exhausted');
        $this->assertSame('quota_exhausted', $campaign->fresh()->suspended_reason);
    }

    public function test_service_rejects_transition_outside_the_table(): void
    {
        $campaign = Campaign::factory()->create(['status' => 'completed']);

        $this->expectException(InvalidCampaignTransitionException::class);
        app(CampaignService::class)->suspend($campaign, CampaignSuspendedReason::Manual);
    }
}
