<?php

namespace Tests\Feature\Modules\DeliveryEngine\Rendering;

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Composer\Models\Draft;
use App\Modules\Composer\Models\Signature;
use App\Modules\DeliveryEngine\Rendering\Contracts\RenderingStage;
use App\Modules\DeliveryEngine\Rendering\RenderContext;
use App\Modules\DeliveryEngine\Rendering\RenderedEmail;
use App\Modules\DeliveryEngine\Rendering\RenderingPipeline;
use App\Modules\Identity\Models\User;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Senders\Models\Sender;
use App\Modules\Tracking\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * docs/38 §38.2 / docs/45 §45.6.3 — golden-file tests of the whole rendering
 * pipeline. Inputs: tests/Fixtures/rendering/<case>.input.html. Expected:
 * <case>.personalised.html (stored messages.html_body), <case>.expected.html
 * (sent HTML), <case>.expected.txt (plain-text part), <case>.expected.json
 * (subject, To name, inline image cids).
 *
 * Volatile parts are normalised before comparison: the app URL becomes
 * `{APP_URL}` and URL signatures `{SIG}` (time is frozen, so `expires` is
 * stable). Regenerate with `UPDATE_GOLDEN=1`.
 */
class RenderingGoldenTest extends TestCase
{
    use RefreshDatabase;

    public const MESSAGE_UUID = '11111111-2222-4333-8444-555555555555';

    /** 1×1 transparent PNG. */
    public const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-20 10:00:00', 'UTC'));
        Storage::fake('public');
        Storage::disk('public')->put('rendering/logo.png', base64_decode(self::PNG));
    }

    public function test_campaign_full_golden(): void
    {
        $sender = Sender::factory()->create(['name' => 'Équipe Marketing', 'email' => 'marketing@example.com']);
        $campaign = Campaign::factory()->create([
            'name' => "Soldes d'été",
            'subject' => '{{ contact.PRENOM }}, nos soldes {{ campaign.NAME }}',
            'sender_id' => $sender->id,
            'to_field' => '{FNAME} {{ contact.NOM }}',
            'utm_enabled' => true,
            'utm_source' => 'newsletter',
            'utm_medium' => 'email',
            'utm_campaign' => null,
            'mirror_link_enabled' => true,
            'inline_images' => true,
            'tracking_enabled' => true,
        ]);
        $recipient = Recipient::factory()->create([
            'email' => 'amina@example.dz', 'first_name' => 'Amina', 'last_name' => 'Benali & Fils', 'custom_fields' => [],
        ]);

        $this->assertGolden('campaign-full', (string) $campaign->subject, $recipient, $campaign);
    }

    public function test_draft_with_signature_golden(): void
    {
        $user = User::factory()->create();
        $signature = Signature::query()->create([
            'user_id' => $user->id, 'name' => 'Pro', 'is_default' => true,
            'html_content' => '<p>--<br>Karim · <a href="https://www.linkedin.com/in/karim">LinkedIn</a></p>',
        ]);
        $draft = Draft::query()->create([
            'user_id' => $user->id, 'subject' => 'Votre devis', 'html_body' => '', 'signature_id' => $signature->id,
        ]);
        $recipient = Recipient::factory()->create(['email' => 'client@example.com', 'first_name' => null, 'custom_fields' => []]);

        $this->assertGolden('draft-signature', 'Votre devis', $recipient, null, $draft);
    }

    public function test_tracking_disabled_golden(): void
    {
        $campaign = Campaign::factory()->create([
            'name' => 'Sans suivi',
            'subject' => 'Bonjour',
            'utm_enabled' => true,
            'utm_source' => 'x',
            'mirror_link_enabled' => false,
            'tracking_enabled' => false,
        ]);
        $recipient = Recipient::factory()->create(['email' => 'nora@example.com', 'first_name' => 'Nora', 'custom_fields' => []]);

        $this->assertGolden('tracking-off', 'Bonjour', $recipient, $campaign);
    }

    private function assertGolden(string $case, string $subject, Recipient $recipient, ?Campaign $campaign = null, ?Draft $draft = null): void
    {
        $pipeline = app(RenderingPipeline::class);
        $html = (string) file_get_contents($this->fixture($case.'.input.html'));

        $message = new Message;
        $message->forceFill(['uuid' => self::MESSAGE_UUID, 'campaign_id' => $campaign?->id, 'recipient_id' => $recipient->id]);

        $personalised = $pipeline->run(
            new RenderedEmail($subject, $html),
            new RenderContext($recipient, $message, $campaign, $draft),
            RenderingStage::PHASE_PERSONALISE,
        );
        $final = $pipeline->run($personalised, new RenderContext($recipient, $message, $campaign, $draft), RenderingStage::PHASE_DELIVERY);

        // The two-phase split equals a single full run.
        $full = $pipeline->renderFull($subject, $html, new RenderContext($recipient, $message, $campaign, $draft));
        $this->assertSame($final->html, $full->html);
        $this->assertSame($final->text, $full->text);

        // Idempotent: rendering the rendered output again changes nothing.
        $again = $pipeline->run($final, new RenderContext($recipient, $message, $campaign, $draft));
        $this->assertSame($final->html, $again->html);
        $this->assertSame($final->text, $again->text);
        $this->assertSame($final->subject, $again->subject);

        $meta = json_encode([
            'subject' => $final->subject,
            'to_name' => $final->toName,
            'inline_images' => array_map(fn ($i) => ['cid' => $i->cid, 'mime' => $i->mimeType, 'bytes' => strlen($i->contents)], $final->inlineImages),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";

        $this->compare($case.'.personalised.html', $this->normalise($personalised->html));
        $this->compare($case.'.expected.html', $this->normalise($final->html));
        $this->compare($case.'.expected.txt', $this->normalise((string) $final->text)."\n");
        $this->compare($case.'.expected.json', $this->normalise($meta));
    }

    private function compare(string $file, string $actual): void
    {
        $path = $this->fixture($file);

        if (getenv('UPDATE_GOLDEN') === '1' || ! is_file($path)) {
            file_put_contents($path, $actual);
            $this->markTestIncomplete("Golden file written: {$file}");
        }

        $this->assertSame((string) file_get_contents($path), $actual, "Golden mismatch: {$file}");
    }

    private function normalise(string $value): string
    {
        $base = rtrim(url('/'), '/');
        $value = str_replace([$base, rawurlencode($base)], ['{APP_URL}', rawurlencode('{APP_URL}')], $value);

        return (string) preg_replace('/signature(=|%3D)[0-9a-f]{64}/', 'signature$1{SIG}', $value);
    }

    private function fixture(string $file): string
    {
        return base_path('tests/Fixtures/rendering/'.$file);
    }
}
