<?php

namespace Tests\Feature\Modules\Forms;

use App\Modules\Forms\Contracts\CaptchaVerifier;
use App\Modules\Forms\Events\FormSubmitted;
use App\Modules\Forms\Models\Form;
use App\Modules\Forms\Models\FormSubmission;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Recipients\Models\RecipientList;
use App\Modules\Suppression\Preferences\Mail\DoubleOptInMail;
use App\Modules\Suppression\Models\SuppressionEntry;
use Database\Factories\RecipientListFactory;
use Database\Seeders\ContactAttributeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** docs/45 §45.7.4 — WP-90 public hosted form + submit. */
class PublicFormTest extends TestCase
{
    use RefreshDatabase;

    private RecipientList $list;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContactAttributeSeeder::class);
        $this->list = RecipientListFactory::new()->create();
    }

    private function form(array $state = [], array $settings = []): Form
    {
        return Form::factory()->withSettings($settings)->create(array_merge(['list_ids' => [$this->list->uuid]], $state));
    }

    /** @param  array<string, mixed>  $extra */
    private function submit(Form $form, array $extra = [], string $email = 'amina@exemple.dz')
    {
        return $this->postJson("/f/{$form->uuid}", array_merge(['attributes' => ['EMAIL' => $email, 'PRENOM' => 'Amina']], $extra));
    }

    public function test_show_renders_the_form_and_branding(): void
    {
        $form = $this->form(['name' => 'Inscription']);
        $this->get("/f/{$form->uuid}")->assertOk()
            ->assertSee('attributes[EMAIL]', false)->assertSee('name="website"', false)->assertSee('Prénom');
    }

    public function test_inactive_draft_and_unknown_forms_are_404(): void
    {
        $this->get('/f/'.$this->form(['status' => 'inactive'])->uuid)->assertNotFound();
        $this->get('/f/'.$this->form(['status' => 'draft'])->uuid)->assertNotFound();
        $this->get('/f/'.\Illuminate\Support\Str::uuid())->assertNotFound();
        $inactive = $this->form(['status' => 'inactive']);
        $this->submit($inactive)->assertNotFound();
        $this->get("/f/{$inactive->uuid}/embed.js")->assertNotFound();
        $this->get("/f/{$inactive->uuid}/merci")->assertNotFound();
    }

    public function test_valid_submit_with_direct_opt_in(): void
    {
        Event::fake([FormSubmitted::class]);
        Mail::fake();
        $form = $this->form();

        $this->submit($form)->assertOk()->assertJsonPath('data.status', 'subscribed');

        $recipient = Recipient::query()->where('email', 'amina@exemple.dz')->firstOrFail();
        $this->assertSame('form', $recipient->source);
        $this->assertSame('Amina', $recipient->first_name);
        $row = DB::table('recipients')->where('id', $recipient->id)->first();
        $this->assertNotNull($row->opt_in_at);
        $this->assertSame('form', $row->opt_in_source);
        $this->assertTrue(DB::table('recipient_list_recipient')->where('recipient_list_id', $this->list->id)->where('recipient_id', $recipient->id)->exists());
        Mail::assertNothingSent();

        $submission = FormSubmission::query()->firstOrFail();
        $this->assertSame('subscribed', $submission->status);
        $this->assertSame(hash('sha256', '127.0.0.1'), $submission->ip_hash);
        $this->assertSame(['PRENOM' => 'Amina'], $submission->data);
        $this->assertSame(1, $form->refresh()->submissions_count);
        $this->assertNotNull($form->last_submission_at);

        Event::assertDispatched(FormSubmitted::class, fn (FormSubmitted $e) => $e->formId === $form->id
            && $e->submissionId === $submission->id && $e->recipientId === $recipient->id && $e->status === 'subscribed');
    }

    public function test_double_opt_in_sends_mail_and_marks_pending(): void
    {
        Mail::fake();
        $form = $this->form([], ['double_opt_in' => true]);

        $this->submit($form)->assertOk()->assertJsonPath('data.status', 'pending_confirmation');

        $recipient = Recipient::query()->where('email', 'amina@exemple.dz')->firstOrFail();
        $row = DB::table('recipients')->where('id', $recipient->id)->first();
        $this->assertNull($row->opt_in_at);
        $this->assertSame('form_pending', $row->opt_in_source);
        Mail::assertQueued(DoubleOptInMail::class, 1);
        $submission = FormSubmission::query()->firstOrFail();
        $this->assertSame('pending_confirmation', $submission->status);
        $this->assertSame(['PRENOM' => 'Amina'], $submission->data);
        // Nothing reaches the contact before the confirmation.
        $this->assertFalse(DB::table('recipient_list_recipient')->where('recipient_id', $recipient->id)->exists());
        $this->assertNull($recipient->first_name);
    }

    public function test_existing_contact_is_merged_not_duplicated(): void
    {
        Mail::fake();
        Recipient::factory()->create(['email' => 'amina@exemple.dz', 'first_name' => 'Old', 'source' => 'manual']);
        $this->submit($this->form())->assertOk();

        $this->assertSame(1, Recipient::query()->where('email', 'amina@exemple.dz')->count());
        $this->assertSame('manual', Recipient::query()->where('email', 'amina@exemple.dz')->value('source'));
    }

    public function test_missing_required_and_invalid_email_and_gdpr(): void
    {
        $form = $this->form(['fields' => [
            ['attribute' => 'EMAIL', 'label' => 'Email', 'required' => true],
            ['attribute' => 'NOM', 'label' => 'Nom', 'required' => true],
        ], 'gdpr' => ['enabled' => true, 'text' => 'OK', 'required' => true]]);

        $this->postJson("/f/{$form->uuid}", ['attributes' => ['EMAIL' => 'pas-un-email']])
            ->assertUnprocessable()->assertJsonValidationErrors(['email', 'attributes.NOM', 'gdpr_consent']);
        $this->assertSame(0, Recipient::query()->count());

        $this->postJson("/f/{$form->uuid}", ['attributes' => ['EMAIL' => 'a@b.dz', 'NOM' => 'X'], 'gdpr_consent' => '1'])->assertOk();
    }

    public function test_honeypot_is_silently_dropped(): void
    {
        Event::fake([FormSubmitted::class]);
        $this->submit($this->form(), ['website' => 'http://spam.example'])->assertOk()->assertJsonPath('data.status', 'subscribed');

        $this->assertSame(0, Recipient::query()->count());
        $this->assertSame('spam', FormSubmission::query()->firstOrFail()->status);
        Event::assertNotDispatched(FormSubmitted::class);
    }

    public function test_submit_is_throttled_per_ip(): void
    {
        Mail::fake();
        $form = $this->form();
        for ($i = 0; $i < 5; $i++) {
            $this->submit($form, [], "u$i@exemple.dz")->assertOk();
        }
        $this->submit($form, [], 'u6@exemple.dz')->assertStatus(429);
    }

    public function test_captcha_failure_blocks_submission(): void
    {
        $this->app->instance(CaptchaVerifier::class, new class implements CaptchaVerifier
        {
            public function verify(?string $token, ?string $ip): bool
            {
                return $token === 'good';
            }
        });
        $form = $this->form([], ['captcha_enabled' => true]);

        $this->submit($form)->assertUnprocessable()->assertJsonValidationErrors(['captcha_token']);
        $this->assertSame(0, Recipient::query()->count());
        $this->submit($form, ['captcha_token' => 'good'])->assertOk();
    }

    public function test_blocklisted_address_gets_the_normal_answer_and_nothing_happens(): void
    {
        Event::fake([FormSubmitted::class]);
        Mail::fake();
        SuppressionEntry::factory()->create(['email' => 'amina@exemple.dz']);

        $this->submit($this->form())->assertOk()->assertJsonPath('data.status', 'subscribed');
        $this->submit($this->form([], ['double_opt_in' => true]))->assertOk()->assertJsonPath('data.status', 'pending_confirmation');

        $this->assertSame(0, Recipient::query()->count());
        $this->assertSame(0, FormSubmission::query()->count());
        Mail::assertNothingQueued();
        Event::assertNotDispatched(FormSubmitted::class);
    }

    public function test_suppressed_contact_gets_the_normal_answer_and_is_left_untouched(): void
    {
        Mail::fake();
        $recipient = Recipient::factory()->create(['email' => 'amina@exemple.dz', 'first_name' => 'Old', 'status' => 'suppressed']);

        $this->submit($this->form())->assertOk()->assertJsonPath('data.status', 'subscribed');

        $this->assertSame('Old', $recipient->fresh()->first_name);
        $this->assertFalse(DB::table('recipient_list_recipient')->where('recipient_id', $recipient->id)->exists());
        $this->assertSame(0, FormSubmission::query()->count());
        Mail::assertNothingQueued();
    }

    public function test_html_submit_redirects_to_thank_you_or_custom_url(): void
    {
        Mail::fake();
        $form = $this->form();
        $this->post("/f/{$form->uuid}", ['attributes' => ['EMAIL' => 'a@b.dz']])->assertRedirect("/f/{$form->uuid}/merci");
        $this->get("/f/{$form->uuid}/merci")->assertOk()->assertSee('Merci');

        $custom = $this->form([], ['redirect_url' => 'https://exemple.dz/merci']);
        $this->post("/f/{$custom->uuid}", ['attributes' => ['EMAIL' => 'c@d.dz']])->assertRedirect('https://exemple.dz/merci');
    }

    public function test_embed_script_content_type_and_content(): void
    {
        $form = $this->form();
        $r = $this->get("/f/{$form->uuid}/embed.js")->assertOk();
        $this->assertStringStartsWith('application/javascript', $r->headers->get('Content-Type'));
        $this->assertStringContainsString('data-pjm-form', $r->getContent());
        $this->assertStringContainsString($form->uuid, $r->getContent());
        $this->assertStringContainsString('"attribute":"EMAIL"', $r->getContent());
    }
}
