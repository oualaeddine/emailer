<?php

namespace Tests\Feature\Modules\Campaigns\AbTesting;

use App\Modules\DeliveryEngine\Services\SmtpManagerContract;
use App\Modules\DeliveryEngine\Jobs\SendEmailJob;
use App\Modules\Tracking\Models\Message;
use App\Modules\DeliveryEngine\Models\SmtpAccount;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Modules\Campaigns\AbTesting\Concerns\BuildsAbTests;
use Tests\Feature\Modules\Campaigns\Concerns\BuildsCampaignFixtures;
use Tests\Support\FakeSmtpManager;
use Tests\TestCase;

/** gap-abvariant — `variant` on `GET /campaigns/{id}/preview` and `POST /campaigns/{id}/test-send`. */
class AbTestVariantPreviewTest extends TestCase
{
    use BuildsAbTests, BuildsCampaignFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        FakeSmtpManager::reset();
        $this->app->bind(SmtpManagerContract::class, FakeSmtpManager::class);
        SmtpAccount::query()->create([
            'name' => 'Compte Test', 'provider' => 'custom', 'host' => 'smtp.example.com', 'port' => 587,
            'encryption' => 'tls', 'username' => 'u', 'password_encrypted' => 'secret',
            'from_email' => 'relay@smtp-provider.dz', 'from_name' => 'Relais',
        ]);
    }

    protected function tearDown(): void
    {
        FakeSmtpManager::reset();
        parent::tearDown();
    }

    public function test_preview_returns_variant_b_subject_preview_and_html(): void
    {
        $owner = $this->operator();
        $campaign = $this->abCampaign($owner, 4, ['type' => 'content'], [
            ['label' => 'A', 'subject' => 'Objet A', 'preview_text' => 'Aperçu A', 'html_body' => '<html><body><p>Contenu A</p></body></html>'],
            ['label' => 'B', 'subject' => 'Objet B', 'preview_text' => 'Aperçu B', 'html_body' => '<html><body><p>Contenu B</p></body></html>'],
        ]);

        $this->actingAs($owner)->getJson("/api/v1/campaigns/{$campaign->uuid}/preview?variant=B")
            ->assertOk()
            ->assertJsonPath('data.subject', 'Objet B')
            ->assertJsonPath('data.preview_text', 'Aperçu B')
            ->assertJsonPath('data.html', '<html><body><p>Contenu B</p></body></html>');

        $this->actingAs($owner)->getJson("/api/v1/campaigns/{$campaign->uuid}/preview")
            ->assertOk()
            ->assertJsonPath('data.subject', $campaign->fresh()->subject);
    }

    public function test_test_send_with_variant_b_delivers_b_subject_and_preheader(): void
    {
        $owner = $this->operator();
        $campaign = $this->abCampaign($owner, 4, [], [
            ['label' => 'A', 'subject' => 'Objet A', 'preview_text' => 'Aperçu A'],
            ['label' => 'B', 'subject' => 'Objet B', 'preview_text' => 'Aperçu B'],
        ]);
        $campaign->update(['html_body' => '<html><body><p>Corps</p></body></html>']);

        $this->actingAs($owner)->postJson("/api/v1/campaigns/{$campaign->uuid}/test-send", [
            'emails' => ['t@exemple.dz'], 'variant' => 'B',
        ])->assertStatus(202);

        $message = Message::query()->where('is_test', true)->sole();
        $this->assertNull($message->variant_id);
        $this->app->call([new SendEmailJob($message->id), 'handle']);

        $this->assertCount(1, FakeSmtpManager::$sent);
        $email = FakeSmtpManager::$sent[0]['email'];
        $this->assertSame('Objet B', $email->getSubject());
        $this->assertStringContainsString('Aperçu B', (string) $email->getHtmlBody());
        $this->assertStringNotContainsString('Aperçu A', (string) $email->getHtmlBody());
    }

    public function test_unknown_variant_and_non_ab_campaign_are_rejected(): void
    {
        $owner = $this->operator();
        $ab = $this->abCampaign($owner, 4);
        $classic = $this->campaignWithAudience($owner, ['lists' => [$this->listWith($this->recipients(2))->id]]);

        $this->actingAs($owner)->getJson("/api/v1/campaigns/{$ab->uuid}/preview?variant=C")
            ->assertStatus(422)->assertJsonValidationErrors('variant');
        $this->actingAs($owner)->postJson("/api/v1/campaigns/{$ab->uuid}/test-send", ['emails' => ['t@exemple.dz'], 'variant' => 'C'])
            ->assertStatus(422)->assertJsonValidationErrors('variant');
        $this->actingAs($owner)->getJson("/api/v1/campaigns/{$classic->uuid}/preview?variant=A")
            ->assertStatus(422)->assertJsonPath('errors.variant.0', "Cette campagne n'a pas de test A/B actif.");
        $this->actingAs($owner)->postJson("/api/v1/campaigns/{$classic->uuid}/test-send", ['emails' => ['t@exemple.dz'], 'variant' => 'B'])
            ->assertStatus(422);
        $this->assertSame(0, Message::query()->where('is_test', true)->count());
    }
}
