<?php

namespace Tests\Feature\Modules\Recipients\Timeline;

use App\Modules\Recipients\Jobs\RecomputeRecipientCountersJob;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Tracking\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecomputeRecipientCountersJobTest extends TestCase
{
    use RefreshDatabase;

    private function msg(Recipient $r, array $attrs): void
    {
        $m = Message::factory()->create(['recipient_id' => $r->id]);
        $m->forceFill($attrs)->save();
    }

    public function test_recomputes_counters_excluding_test_messages(): void
    {
        $r = Recipient::factory()->create(['messages_sent' => 99, 'opened' => 42]);
        $other = Recipient::factory()->create(['messages_sent' => 7]);
        $this->msg($r, ['sent_at' => '2026-10-01 10:00:00', 'delivered_at' => '2026-10-01 10:01:00', 'opened_at' => '2026-10-01 11:00:00', 'clicked_at' => '2026-10-01 12:00:00', 'status' => 'clicked', 'is_test' => false]);
        $this->msg($r, ['sent_at' => '2026-10-02 10:00:00', 'opened_at' => '2026-10-02 11:00:00', 'status' => 'soft_bounced', 'is_test' => false]);
        $this->msg($r, ['sent_at' => '2026-10-03 10:00:00', 'opened_at' => '2026-10-03 11:00:00', 'status' => 'opened', 'is_test' => true]);

        RecomputeRecipientCountersJob::dispatchSync($r->id);

        $r->refresh();
        $this->assertSame(2, (int) $r->messages_sent);
        $this->assertSame(1, (int) $r->delivered);
        $this->assertSame(2, (int) $r->opened);
        $this->assertSame(1, (int) $r->clicked);
        $this->assertSame(1, (int) $r->soft_bounces);
        $this->assertSame('2026-10-02 11:00:00', (string) $r->last_opened_at);
        $this->assertSame(7, (int) $other->fresh()->messages_sent);

        $this->artisan('recipients:recompute-counters')->assertSuccessful();
        $this->assertSame(0, (int) $other->fresh()->messages_sent);
    }
}
