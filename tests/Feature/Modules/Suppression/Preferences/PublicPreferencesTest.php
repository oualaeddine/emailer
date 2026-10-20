<?php

namespace Tests\Feature\Modules\Suppression\Preferences;

use App\Modules\Recipients\Lists\Events\RecipientsRemovedFromList;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Recipients\Models\RecipientList;
use App\Modules\Settings\Services\SettingsService;
use App\Modules\Suppression\Models\SuppressionEntry;
use App\Modules\Suppression\Preferences\Events\ContactPreferencesUpdated;
use App\Modules\Suppression\Preferences\Models\UpdateProfileForm;
use App\Modules\Suppression\Preferences\Services\PreferenceTokenService;
use App\Domain\Enums\SettingValueType;
use Database\Factories\UnsubscribePageFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PublicPreferencesTest extends TestCase
{
    use RefreshDatabase;

    private Recipient $recipient;

    private RecipientList $news;

    private RecipientList $promo;

    protected function setUp(): void
    {
        parent::setUp();
        UpdateProfileForm::query()->delete();
        \App\Modules\Suppression\Preferences\Models\UnsubscribePage::query()->delete();
        $this->recipient = Recipient::factory()->create(['email' => 'amine@exemple.dz']);
        $this->news = \Database\Factories\RecipientListFactory::new()->create(['name' => 'Newsletter']);
        $this->promo = \Database\Factories\RecipientListFactory::new()->create(['name' => 'Promotions']);
        foreach ([$this->news, $this->promo] as $list) {
            DB::table('recipient_list_recipient')->insert([
                'recipient_list_id' => $list->id, 'recipient_id' => $this->recipient->id, 'added_at' => now(), 'source' => 'manual',
            ]);
        }
    }

    private function url(?string $form = null): string
    {
        return app(PreferenceTokenService::class)->preferencesUrl($this->recipient->email, $form);
    }

    private function members(RecipientList $list): bool
    {
        return DB::table('recipient_list_recipient')
            ->where('recipient_list_id', $list->id)->where('recipient_id', $this->recipient->id)->exists();
    }

    public function test_valid_token_renders_lists_texts_and_branding(): void
    {
        app(SettingsService::class)->setSystemValue('branding.brand_color', '#AB1234', SettingValueType::String);
        UpdateProfileForm::factory()->default()->create([
            'content' => ['title' => 'Vos préférences', 'intro' => 'Choisissez', 'submit_label' => 'Valider', 'success_message' => 'Merci !'],
            'design' => ['primary_color' => null],
        ]);

        $this->get($this->url())
            ->assertOk()
            ->assertSee('Vos préférences')
            ->assertSee('Newsletter')
            ->assertSee('Promotions')
            ->assertSee('Valider')
            ->assertSee('amine@exemple.dz');
    }

    public function test_brand_colour_is_used_when_design_has_no_override(): void
    {
        app(SettingsService::class)->setSystemValue('branding.brand_color', '#AB1234', SettingValueType::String);
        UpdateProfileForm::factory()->default()->create(['design' => ['logo_url' => null]]);

        $this->get($this->url())->assertOk()->assertSee('#AB1234', false);
    }

    public function test_form_can_pin_the_offered_lists_and_texts_are_escaped(): void
    {
        $form = UpdateProfileForm::factory()->create([
            'list_ids' => [$this->news->uuid],
            'content' => ['title' => '<script>alert(1)</script>', 'intro' => '', 'submit_label' => 'OK', 'success_message' => 'ok'],
        ]);

        $this->get($this->url($form->uuid))
            ->assertOk()
            ->assertSee('Newsletter')
            ->assertDontSee('Promotions')
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_tampered_or_expired_signature_is_rejected(): void
    {
        $this->get($this->url().'x')->assertForbidden();

        $expired = URL::temporarySignedRoute('preferences.show', now()->subMinute(), ['token' => \App\Modules\Suppression\Support\UnsubscribeUrlGenerator::encode('amine@exemple.dz')]);
        $this->get($expired)->assertForbidden();
        $this->post($expired)->assertForbidden();

        $this->get('/preferences/'.\App\Modules\Suppression\Support\UnsubscribeUrlGenerator::encode('amine@exemple.dz'))->assertForbidden();
    }

    public function test_validly_signed_but_malformed_or_unknown_token_is_404_with_message(): void
    {
        $bad = URL::signedRoute('preferences.show', ['token' => 'not-an-email'], now()->addDay());
        $this->get($bad)->assertNotFound()->assertSee('invalide');

        $unknown = app(PreferenceTokenService::class)->preferencesUrl('inconnu@exemple.dz');
        $this->get($unknown)->assertNotFound();
        $this->post($unknown)->assertNotFound();
    }

    public function test_per_list_opt_out_removes_membership_only_for_unchecked_lists(): void
    {
        Event::fake([RecipientsRemovedFromList::class, ContactPreferencesUpdated::class]);
        UpdateProfileForm::factory()->default()->create([
            'content' => ['title' => 'T', 'intro' => '', 'submit_label' => 'OK', 'success_message' => 'Préférences enregistrées !'],
        ]);

        $this->from($this->url())->post($this->url(), ['list_ids' => [$this->news->uuid]])
            ->assertRedirect($this->url())
            ->assertSessionHas('preferences_message', 'Préférences enregistrées !');

        $this->assertTrue($this->members($this->news));
        $this->assertFalse($this->members($this->promo));
        $this->assertDatabaseMissing('suppression_entries', ['email' => 'amine@exemple.dz']);
        Event::assertDispatched(RecipientsRemovedFromList::class, fn ($e) => $e->listId === $this->promo->id && $e->source === 'preferences');
        Event::assertDispatched(ContactPreferencesUpdated::class, fn ($e) => $e->unsubscribedListIds === [$this->promo->id] && ! $e->unsubscribedAll);
    }

    public function test_opt_in_to_an_offered_list(): void
    {
        $other = \Database\Factories\RecipientListFactory::new()->create(['name' => 'Événements']);
        UpdateProfileForm::factory()->default()->create(['list_ids' => [$this->news->uuid, $other->uuid]]);

        $this->post($this->url(), ['list_ids' => [$this->news->uuid, $other->uuid]])->assertRedirect();

        $this->assertTrue($this->members($other));
        // promo is not offered by the form: untouched.
        $this->assertTrue($this->members($this->promo));
    }

    public function test_unsubscribe_all_suppresses_and_leaves_every_list(): void
    {
        \App\Modules\Suppression\Preferences\Models\UnsubscribePage::factory()->default()->create([
            'content' => array_merge(\App\Modules\Suppression\Preferences\Services\PageDefaults::unsubscribeContent(), ['confirmation_message' => 'À bientôt']),
        ]);

        $this->post($this->url(), ['unsubscribe_all' => '1', 'list_ids' => [$this->news->uuid]])
            ->assertRedirect()
            ->assertSessionHas('preferences_message', 'À bientôt');

        $this->assertFalse($this->members($this->news));
        $this->assertFalse($this->members($this->promo));
        $this->assertSame('global_unsubscribe', SuppressionEntry::where('email', 'amine@exemple.dz')->value('reason'));
    }

    public function test_attributes_outside_the_form_are_ignored_and_listed_ones_saved(): void
    {
        UpdateProfileForm::factory()->default()->create([
            'fields' => [['attribute' => 'PRENOM', 'label' => 'Prénom', 'required' => false]],
        ]);
        $this->seed(\Database\Seeders\ContactAttributeSeeder::class);

        $this->post($this->url(), [
            'list_ids' => [$this->news->uuid, $this->promo->uuid],
            'attributes' => ['PRENOM' => 'Amine', 'NOM' => 'Hacker'],
        ])->assertRedirect();

        $this->recipient->refresh();
        $this->assertSame('Amine', $this->recipient->first_name);
        $this->assertNotSame('Hacker', $this->recipient->last_name);
    }

    public function test_post_is_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->post($this->url(), ['list_ids' => [$this->news->uuid, $this->promo->uuid]])->assertRedirect();
        }
        $this->post($this->url(), ['list_ids' => []])->assertStatus(429);
    }
}
