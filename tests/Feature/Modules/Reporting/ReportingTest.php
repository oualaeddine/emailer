<?php

namespace Tests\Feature\Modules\Reporting;

use App\Domain\Enums\MessageStatus;
use App\Domain\Enums\RoleName;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\DeliveryEngine\Models\SmtpAccount;
use App\Modules\Identity\Models\User;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Tracking\Models\Message;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * docs/25-reporting.md — Reporting module (WP-31).
 *
 * Exercises the summary aggregate endpoint against seeded `Message` rows
 * (mirroring `CampaignController::analytics()`'s aggregation style, see
 * App\Modules\Reporting\Services\ReportingService), permission gating on
 * both `reporting.view` and `reporting.export` (§25.6 — Viewer gets view
 * only), and the CSV export's content-type/row shape.
 */
class ReportingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function createCampaign(string $name = 'Campagne Test'): Campaign
    {
        return Campaign::query()->create([
            'name' => $name,
            'status' => 'completed',
            'smtp_strategy' => 'single',
            'send_mode' => 'immediate',
            'tracking_enabled' => true,
        ]);
    }

    /** `SmtpAccount` has no factory (no `HasFactory` trait, no `SmtpAccountFactory` class exists in this repo). */
    private function createSmtpAccount(string $name = 'SMTP Test'): SmtpAccount
    {
        return SmtpAccount::query()->create([
            'name' => $name,
            'provider' => 'smtp',
            'host' => 'smtp.example.test',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'user@example.test',
            'password_encrypted' => 'secret',
            'from_email' => 'no-reply@example.test',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function messageAttributes(?Campaign $campaign, ?SmtpAccount $smtpAccount, MessageStatus $status, ?\Illuminate\Support\Carbon $queuedAt = null): array
    {
        $attributes = [
            'recipient_id' => Recipient::factory()->create()->id,
            'campaign_id' => $campaign?->id,
            'smtp_account_id' => $smtpAccount?->id,
            'subject' => 'Objet de test',
            'html_body' => '<p>Bonjour</p>',
            'status' => $status->value,
            'queued_at' => $queuedAt ?? now(),
            'open_count' => 0,
            'click_count' => 0,
        ];

        return match ($status) {
            MessageStatus::Delivered => $attributes + ['delivered_at' => now()],
            MessageStatus::Opened => $attributes + ['delivered_at' => now(), 'opened_at' => now(), 'open_count' => 2],
            MessageStatus::Clicked => $attributes + ['delivered_at' => now(), 'opened_at' => now(), 'clicked_at' => now(), 'open_count' => 1, 'click_count' => 3],
            MessageStatus::SoftBounced, MessageStatus::HardBounced => $attributes + ['bounced_at' => now()],
            MessageStatus::Failed => $attributes + ['failed_at' => now()],
            default => $attributes,
        };
    }

    public function test_summary_returns_correct_aggregate_counts_from_seeded_messages(): void
    {
        $viewer = User::factory()->withRole(RoleName::Viewer)->create();
        $campaign = $this->createCampaign();

        // 5 messages total: 1 delivered, 1 opened, 1 clicked, 1 soft-bounced, 1 failed.
        Message::query()->create($this->messageAttributes($campaign, null, MessageStatus::Delivered));
        Message::query()->create($this->messageAttributes($campaign, null, MessageStatus::Opened));
        Message::query()->create($this->messageAttributes($campaign, null, MessageStatus::Clicked));
        Message::query()->create($this->messageAttributes($campaign, null, MessageStatus::SoftBounced));
        Message::query()->create($this->messageAttributes($campaign, null, MessageStatus::Failed));

        $response = $this->actingAs($viewer)->getJson('/api/v1/reporting/summary');

        $response->assertOk();
        $response->assertJsonPath('sent', 5);
        // delivered_at is stamped for delivered/opened/clicked (3 messages).
        $response->assertJsonPath('delivered', 3);
        $response->assertJsonPath('unique_opens', 2);
        $response->assertJsonPath('unique_clicks', 1);
        $response->assertJsonPath('bounced', 1);
        $response->assertJsonPath('failed', 1);
        // open_count sums: opened(2) + clicked(1) = 3; click_count sums: clicked(3) = 3.
        $response->assertJsonPath('open_count', 3);
        $response->assertJsonPath('click_count', 3);
        $response->assertJsonPath('delivery_rate', 0.6);
        $response->assertJsonPath('by_status.delivered', 1);
        $response->assertJsonPath('by_status.opened', 1);
        $response->assertJsonPath('by_status.clicked', 1);
        $response->assertJsonPath('by_status.soft_bounced', 1);
        $response->assertJsonPath('by_status.failed', 1);
    }

    public function test_summary_can_be_filtered_by_campaign_and_smtp_account(): void
    {
        $viewer = User::factory()->withRole(RoleName::Viewer)->create();
        $campaignA = $this->createCampaign('Campagne A');
        $campaignB = $this->createCampaign('Campagne B');
        $smtpAccount = $this->createSmtpAccount();

        Message::query()->create($this->messageAttributes($campaignA, $smtpAccount, MessageStatus::Delivered));
        Message::query()->create($this->messageAttributes($campaignA, $smtpAccount, MessageStatus::Delivered));
        Message::query()->create($this->messageAttributes($campaignB, null, MessageStatus::Delivered));

        $response = $this->actingAs($viewer)->getJson("/api/v1/reporting/summary?campaign_id={$campaignA->uuid}");
        $response->assertOk();
        $response->assertJsonPath('sent', 2);

        $response = $this->actingAs($viewer)->getJson("/api/v1/reporting/summary?smtp_account_id={$smtpAccount->uuid}");
        $response->assertOk();
        $response->assertJsonPath('sent', 2);
    }

    public function test_summary_respects_date_range_filters(): void
    {
        $viewer = User::factory()->withRole(RoleName::Viewer)->create();
        $campaign = $this->createCampaign();

        Message::query()->create($this->messageAttributes($campaign, null, MessageStatus::Delivered, now()->subDays(10)));
        Message::query()->create($this->messageAttributes($campaign, null, MessageStatus::Delivered, now()));

        $response = $this->actingAs($viewer)->getJson(
            '/api/v1/reporting/summary?date_from='.now()->subDay()->toDateString().'&date_to='.now()->toDateString(),
        );

        $response->assertOk();
        $response->assertJsonPath('sent', 1);
    }

    public function test_campaign_breakdown_endpoint_returns_per_campaign_rows(): void
    {
        $manager = User::factory()->withRole(RoleName::MarketingManager)->create();
        $campaign = $this->createCampaign('Campagne X');

        Message::query()->create($this->messageAttributes($campaign, null, MessageStatus::Delivered));
        Message::query()->create($this->messageAttributes($campaign, null, MessageStatus::SoftBounced));

        $response = $this->actingAs($manager)->getJson('/api/v1/reporting/campaigns');

        $response->assertOk();
        $response->assertJsonFragment(['campaign_name' => 'Campagne X', 'sent' => 2, 'delivered' => 1, 'bounced' => 1]);
    }

    public function test_smtp_account_breakdown_endpoint_returns_per_account_rows(): void
    {
        $manager = User::factory()->withRole(RoleName::MarketingManager)->create();
        $smtpAccount = $this->createSmtpAccount('SMTP Principal');
        $campaign = $this->createCampaign();

        Message::query()->create($this->messageAttributes($campaign, $smtpAccount, MessageStatus::Delivered));

        $response = $this->actingAs($manager)->getJson('/api/v1/reporting/smtp-accounts');

        $response->assertOk();
        $response->assertJsonFragment(['smtp_account_name' => 'SMTP Principal', 'sent' => 1, 'delivered' => 1]);
    }

    public function test_viewer_can_read_summary_but_not_export(): void
    {
        $viewer = User::factory()->withRole(RoleName::Viewer)->create();

        $this->actingAs($viewer)->getJson('/api/v1/reporting/summary')->assertOk();
        $this->actingAs($viewer)->getJson('/api/v1/reporting/export')->assertForbidden();
    }

    public function test_marketing_operator_can_read_and_export(): void
    {
        $operator = User::factory()->withRole(RoleName::MarketingOperator)->create();

        $this->actingAs($operator)->getJson('/api/v1/reporting/summary')->assertOk();
        $this->actingAs($operator)->getJson('/api/v1/reporting/export')->assertOk();
    }

    public function test_guest_cannot_read_reporting_summary(): void
    {
        $this->getJson('/api/v1/reporting/summary')->assertUnauthorized();
    }

    public function test_export_returns_csv_content_type_and_expected_rows(): void
    {
        $admin = User::factory()->withRole(RoleName::Administrator)->create();
        $campaign = $this->createCampaign('Campagne Export');

        Message::query()->create($this->messageAttributes($campaign, null, MessageStatus::Delivered));
        Message::query()->create($this->messageAttributes($campaign, null, MessageStatus::Delivered));

        $response = $this->actingAs($admin)->get('/api/v1/reporting/export');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();
        $lines = array_values(array_filter(explode("\n", str_replace("\r\n", "\n", $csv))));

        $this->assertSame(
            'campaign_id,campaign_name,sent,delivered,unique_opens,unique_clicks,bounced,delivery_rate,open_rate,click_rate,bounce_rate',
            $lines[0],
        );
        $this->assertStringContainsString('Campagne Export', $lines[1]);
        $this->assertStringContainsString(',2,2,', $lines[1]);
    }
}
