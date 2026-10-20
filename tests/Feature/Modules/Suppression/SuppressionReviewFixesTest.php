<?php

namespace Tests\Feature\Modules\Suppression;

use App\Domain\Enums\RoleName;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Identity\Models\User;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Suppression\Preferences\Mail\DoubleOptInMail;
use App\Modules\Suppression\Preferences\Models\UnsubscribePage;
use App\Modules\Suppression\Preferences\Models\UpdateProfileForm;
use App\Modules\Suppression\Preferences\Services\PreferenceTokenService;
use App\Modules\Suppression\Support\UnsubscribeUrlGenerator;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Wave 3 review fixes: S5, S12 (WP-74 contract), N9. */
class SuppressionReviewFixesTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_in_use_by_running_paused_or_suspended_campaign_cannot_be_deleted(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->withRole(RoleName::Administrator)->create();
        UnsubscribePage::factory()->default()->create();
        $page = UnsubscribePage::factory()->create();
        $campaign = Campaign::factory()->create();

        foreach (['running', 'paused', 'suspended'] as $status) {
            DB::table('campaigns')->where('id', $campaign->id)->update(['status' => $status, 'unsubscribe_page_id' => $page->id]);
            $this->actingAs($admin)->deleteJson("/api/v1/unsubscribe-pages/{$page->uuid}")->assertStatus(409);
        }
    }

    public function test_unsubscribe_get_renders_the_default_page_as_html_and_json_for_api_clients(): void
    {
        UnsubscribePage::query()->delete();
        UnsubscribePage::factory()->default()->create(['content' => ['title' => 'Au revoir ?', 'button_label' => 'Oui, partir']]);
        $url = app(UnsubscribeUrlGenerator::class)->signedUrlFor('client@example.com');

        $this->get($url)->assertOk()->assertSee('Au revoir ?')->assertSee('Oui, partir')->assertSee('client@example.com');
        $this->getJson($url)->assertOk()->assertJsonPath('data.email', 'client@example.com');
    }

    public function test_unsubscribe_get_uses_the_campaign_page_when_given(): void
    {
        UnsubscribePage::query()->delete();
        UnsubscribePage::factory()->default()->create(['content' => ['title' => 'Page par défaut']]);
        $page = UnsubscribePage::factory()->create(['content' => ['title' => 'Page de la campagne']]);
        $campaign = Campaign::factory()->create();
        DB::table('campaigns')->where('id', $campaign->id)->update(['unsubscribe_page_id' => $page->id]);

        $url = app(UnsubscribeUrlGenerator::class)->signedUrlFor('client@example.com');
        $signed = \Illuminate\Support\Facades\URL::signedRoute('unsubscribe.show', ['token' => UnsubscribeUrlGenerator::encode('client@example.com'), 'campaign' => $campaign->uuid]);

        $this->get($signed)->assertSee('Page de la campagne');
        $this->get($url)->assertSee('Page par défaut');
    }

    public function test_unsubscribe_post_persists_the_survey_reason(): void
    {
        $recipient = Recipient::factory()->create(['email' => 'client@example.com']);
        $url = app(UnsubscribeUrlGenerator::class)->signedUrlFor('client@example.com');

        $this->postJson($url, ['reason' => 'Autre', 'reason_other' => 'Trop de mails le lundi'])->assertOk();
        $this->post($url, ['reason' => 'Je reçois trop d’e-mails'])->assertOk()->assertSee('désinscrit');

        $rows = DB::table('unsubscribe_feedback')->orderBy('id')->get();
        $this->assertCount(2, $rows);
        $this->assertSame('Autre: Trop de mails le lundi', $rows[0]->reason);
        $this->assertSame($recipient->id, $rows[0]->recipient_id);
        $this->assertSame('Je reçois trop d’e-mails', $rows[1]->reason);

        $this->postJson($url)->assertOk();
        $this->assertSame(2, DB::table('unsubscribe_feedback')->count());
    }

    public function test_double_opt_in_endpoint_sends_mail_requires_permission_and_is_throttled(): void
    {
        $this->seed(RolePermissionSeeder::class);
        Mail::fake();
        $recipient = Recipient::factory()->create(['email' => 'nadia@exemple.dz']);
        $manager = User::factory()->withRole(RoleName::Administrator)->create();
        $viewer = User::factory()->withRole(RoleName::Viewer)->create();
        $url = "/api/v1/recipients/{$recipient->uuid}/double-opt-in";

        $this->actingAs($viewer)->postJson($url)->assertForbidden();
        $this->actingAs($manager)->postJson($url)->assertStatus(202);
        Mail::assertQueued(DoubleOptInMail::class, fn (DoubleOptInMail $m) => $m->hasTo('nadia@exemple.dz'));

        for ($i = 0; $i < 4; $i++) {
            $this->actingAs($manager)->postJson($url)->assertStatus(202);
        }
        $this->actingAs($manager)->postJson($url)->assertStatus(429);
        $this->actingAs($manager)->postJson('/api/v1/recipients/'.\Illuminate\Support\Str::uuid().'/double-opt-in')->assertNotFound();
    }

    public function test_required_profile_fields_are_enforced_server_side(): void
    {
        UpdateProfileForm::query()->delete();
        $recipient = Recipient::factory()->create(['email' => 'amine@exemple.dz']);
        UpdateProfileForm::factory()->default()->create([
            'fields' => [['attribute' => 'PRENOM', 'label' => 'Prénom', 'required' => true]],
        ]);
        $url = app(PreferenceTokenService::class)->preferencesUrl($recipient->email);

        $this->post($url, ['attributes' => ['PRENOM' => '  ']])->assertSessionHasErrors('attributes.PRENOM');
        $this->post($url, ['attributes' => ['PRENOM' => 'Amine']])->assertSessionDoesntHaveErrors();
        $this->post($url, ['unsubscribe_all' => '1'])->assertSessionDoesntHaveErrors();
    }
}
