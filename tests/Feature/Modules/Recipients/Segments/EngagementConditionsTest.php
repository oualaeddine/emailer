<?php

namespace Tests\Feature\Modules\Recipients\Segments;

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Suppression\Models\SuppressionEntry;
use App\Modules\Tracking\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * docs/45-brevo-api-contracts.md §45.5.2 — `engagement.*` conditions
 * (EXISTS on `messages`, campaign filter, last N days / explicit window,
 * min_count, test sends ignored, unsubscribes also from the suppression list).
 *
 * Fixture ("now" = 2026-06-15 12:00 UTC):
 *  A campaign X: sent -5d, opened -4d (3 opens)
 *  B campaign Y: sent -40d, opened -39d (1 open), clicked -39d (2 clicks)
 *  C campaign X: sent -2d, hard bounce -2d
 *  D test send only (opened today) — ignored
 *  E no message; global unsubscribe in the suppression list -3d
 *  F campaign X: sent -3d, unsubscribed -1d
 */
class EngagementConditionsTest extends TestCase
{
    use BuildsRuleTrees;
    use RefreshDatabase;

    private Campaign $x;

    private Campaign $y;

    protected function setUp(): void
    {
        parent::setUp();
        $this->defineSegmentAttributes();
        Carbon::setTestNow(Carbon::parse('2026-06-15 12:00:00', 'UTC'));

        $this->x = Campaign::factory()->create();
        $this->y = Campaign::factory()->create();

        $a = $this->recipient('A');
        $b = $this->recipient('B');
        $c = $this->recipient('C');
        $d = $this->recipient('D');
        $e = $this->recipient('E');
        $f = $this->recipient('F');

        $this->message($a, $this->x, ['status' => 'opened', 'sent_at' => now()->subDays(5), 'opened_at' => now()->subDays(4), 'open_count' => 3]);
        $this->message($b, $this->y, ['status' => 'clicked', 'sent_at' => now()->subDays(40), 'opened_at' => now()->subDays(39), 'open_count' => 1, 'clicked_at' => now()->subDays(39), 'click_count' => 2]);
        $this->message($c, $this->x, ['status' => 'hard_bounced', 'sent_at' => now()->subDays(2), 'bounced_at' => now()->subDays(2)]);
        $this->message($d, $this->x, ['status' => 'opened', 'sent_at' => now(), 'opened_at' => now(), 'open_count' => 1, 'is_test' => true]);
        $this->message($f, $this->x, ['status' => 'unsubscribed', 'sent_at' => now()->subDays(3), 'updated_at' => now()->subDay()]);

        $entry = SuppressionEntry::query()->create(['email' => $e->email, 'reason' => 'global_unsubscribe']);
        DB::table('suppression_entries')->where('id', $entry->id)->update(['created_at' => now()->subDays(3)]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function recipient(string $label): Recipient
    {
        return Recipient::factory()->create(['company_name' => $label]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function message(Recipient $recipient, Campaign $campaign, array $attributes): void
    {
        $message = Message::factory()->create(['recipient_id' => $recipient->id, 'campaign_id' => $campaign->id]);
        $message->forceFill($attributes)->save();
    }

    /**
     * @param  array<string, mixed>  $value
     * @return list<string>
     */
    private function has(string $event, array $value = [], string $operator = 'has'): array
    {
        return $this->labelsFor($this->cond('engagement.'.$event, $operator, $this->engagement($value)));
    }

    public function test_opened_any_campaign_ever_ignores_test_sends(): void
    {
        $this->assertSame(['A', 'B'], $this->has('opened'));
    }

    public function test_within_days_window(): void
    {
        $this->assertSame(['A'], $this->has('opened', ['within_days' => 30]));
        $this->assertSame(['A', 'C', 'F'], $this->has('sent', ['within_days' => 7]));
    }

    public function test_campaign_filter(): void
    {
        $this->assertSame(['A'], $this->has('opened', ['campaign_ids' => [$this->x->uuid]]));
        $this->assertSame(['B'], $this->has('opened', ['campaign_ids' => [$this->y->uuid]]));
        $this->assertSame(['A', 'B'], $this->has('opened', ['campaign_ids' => [$this->x->uuid, $this->y->uuid]]));
    }

    public function test_has_not_is_the_complement(): void
    {
        $this->assertSame(['B', 'C', 'D', 'E', 'F'], $this->has('opened', ['within_days' => 30], 'has_not'));
        $this->assertSame(['C', 'D', 'E', 'F'], $this->has('opened', [], 'has_not'));
    }

    public function test_explicit_from_to_window_overrides_within_days(): void
    {
        $from = now()->subDays(45)->toDateString();
        $to = now()->subDays(30)->toDateString();

        $this->assertSame(['B'], $this->has('opened', ['from' => $from, 'to' => $to, 'within_days' => 7]));
        $this->assertSame(['A'], $this->has('opened', ['from' => now()->subDays(10)->toDateString()]));
    }

    public function test_clicked_and_min_count(): void
    {
        $this->assertSame(['B'], $this->has('clicked'));
        $this->assertSame(['A'], $this->has('opened', ['min_count' => 2]));
        $this->assertSame(['A', 'B'], $this->has('opened', ['min_count' => 1]));
        $this->assertSame(['B'], $this->has('clicked', ['min_count' => 2]));
        $this->assertSame([], $this->has('clicked', ['min_count' => 3]));
        $this->assertSame(['A', 'C', 'D', 'E', 'F'], $this->has('clicked', ['min_count' => 2], 'has_not'));
    }

    public function test_bounces_and_complaints(): void
    {
        $this->assertSame(['C'], $this->has('hard_bounced'));
        $this->assertSame([], $this->has('soft_bounced'));
        $this->assertSame([], $this->has('complained'));
        $this->assertSame([], $this->has('delivered'));
    }

    public function test_unsubscribed_uses_messages_and_suppression_list(): void
    {
        $this->assertSame(['E', 'F'], $this->has('unsubscribed'));
        $this->assertSame(['E', 'F'], $this->has('unsubscribed', ['within_days' => 7]));
        $this->assertSame(['F'], $this->has('unsubscribed', ['campaign_ids' => [$this->x->uuid]]));
        $this->assertSame([], $this->has('unsubscribed', ['to' => now()->subDays(10)->toDateString()]));
    }

    public function test_engagement_combined_with_other_conditions(): void
    {
        $tree = $this->tree([
            $this->group([
                $this->cond('engagement.opened', 'has', $this->engagement(['within_days' => 30])),
                $this->cond('engagement.hard_bounced', 'has', $this->engagement()),
            ], 'or'),
            $this->cond('engagement.sent', 'has', $this->engagement(['campaign_ids' => [$this->x->uuid]])),
        ]);

        $this->assertSame(['A', 'C'], $this->labels($tree));
    }
}
