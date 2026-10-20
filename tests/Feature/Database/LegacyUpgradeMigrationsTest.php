<?php

namespace Tests\Feature\Database;

use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Reporting\Jobs\RollupCampaignStatsJob;
use App\Modules\Tracking\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Data migrations added after the visual pass on an upgraded database:
 * historical campaigns get their recipient count and stats rollup back, and
 * legacy custom_fields keys become visible attributes.
 */
class LegacyUpgradeMigrationsTest extends TestCase
{
    use RefreshDatabase;

    private function runMigration(string $file): void
    {
        $migration = require database_path('migrations/'.$file);
        $migration->up();
    }

    private function messagesFor(Campaign $campaign, array $recipients, bool $isTest = false): void
    {
        foreach ($recipients as $recipient) {
            Message::factory()->create(['campaign_id' => $campaign->id, 'recipient_id' => $recipient->id, 'is_test' => $isTest]);
        }
    }

    public function test_recipients_count_is_backfilled_from_non_test_messages_of_sent_campaigns(): void
    {
        [$a, $b, $c] = Recipient::factory()->count(3)->create()->all();
        $sent = Campaign::factory()->status(CampaignStatus::Completed)->create(['recipients_count' => 0]);
        $this->messagesFor($sent, [$a, $b, $b]);
        $this->messagesFor($sent, [$c], isTest: true);
        $draft = Campaign::factory()->create(['recipients_count' => 0]);
        $this->messagesFor($draft, [$a], isTest: true);

        $this->runMigration('2026_10_24_100001_backfill_recipients_count_for_legacy_campaigns.php');

        $this->assertSame(2, (int) $sent->fresh()->recipients_count);
        $this->assertSame(0, (int) $draft->fresh()->recipients_count);
    }

    public function test_a_final_rollup_is_queued_for_historical_campaigns_without_counters(): void
    {
        Queue::fake();
        $recipient = Recipient::factory()->create();
        $old = Campaign::factory()->status(CampaignStatus::Completed)->create(['sent_count' => 0]);
        $this->messagesFor($old, [$recipient]);
        $counted = Campaign::factory()->status(CampaignStatus::Completed)->create(['sent_count' => 5]);
        $this->messagesFor($counted, [$recipient]);
        Campaign::factory()->status(CampaignStatus::Cancelled)->create(['sent_count' => 0]);

        $this->runMigration('2026_10_24_100002_queue_stats_rollup_for_historical_campaigns.php');

        Queue::assertPushed(RollupCampaignStatsJob::class, 1);
        Queue::assertPushed(RollupCampaignStatsJob::class, fn ($job) => $job->campaignId === $old->id && $job->final);
    }

    public function test_legacy_custom_field_keys_are_registered_as_text_attributes(): void
    {
        Recipient::factory()->create(['custom_fields' => ['wilaya' => 'Oran', 'secteur' => 'BTP', 'clé invalide' => 'x']]);
        Recipient::factory()->create(['custom_fields' => ['WILAYA_2' => 'Blida', 'email' => 'doublon']]);
        $before = DB::table('contact_attributes')->count();

        $this->runMigration('2026_10_24_100003_register_legacy_custom_field_attributes.php');
        $this->runMigration('2026_10_24_100003_register_legacy_custom_field_attributes.php');

        $names = DB::table('contact_attributes')->where('is_system', false)->pluck('name')->all();
        $this->assertEqualsCanonicalizing(['wilaya', 'secteur', 'WILAYA_2'], $names);
        $this->assertSame($before + 3, DB::table('contact_attributes')->count(), 'idempotent, no duplicate of the system EMAIL');
        $this->assertSame('text', DB::table('contact_attributes')->where('name', 'wilaya')->value('type'));
        $this->assertSame('custom_fields', DB::table('contact_attributes')->where('name', 'wilaya')->value('storage'));
    }
}
