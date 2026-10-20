<?php

namespace Tests\Feature\Modules\Forms;

use App\Modules\DeliveryEngine\Jobs\SendEmailJob;
use App\Modules\Forms\Models\Form;
use App\Modules\Forms\Models\FormSubmission;
use App\Domain\Enums\RoleName;
use App\Modules\Identity\Models\User;
use App\Modules\Suppression\Preferences\Mail\DoubleOptInMail;
use App\Modules\Suppression\Support\UnsubscribeUrlGenerator;
use App\Modules\Templates\Models\Template;
use Database\Factories\RecipientListFactory;
use Database\Seeders\ContactAttributeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** next-doi — per-form confirmation e-mail template and redirect after confirmation. */
class FormDoubleOptInTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContactAttributeSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        Bus::fake([SendEmailJob::class]);
        Mail::fake();
    }

    /** @param  array<string, mixed>  $settings */
    private function form(?Template $template, array $settings = []): Form
    {
        return Form::factory()->withSettings(['double_opt_in' => true] + $settings)->create([
            'list_ids' => [RecipientListFactory::new()->create()->uuid],
            'doi_template_id' => $template?->id,
            'fields' => [['attribute' => 'EMAIL', 'label' => 'Email', 'required' => true]],
        ]);
    }

    private function submit(Form $form, string $email = 'amina@exemple.dz'): void
    {
        $this->postJson("/f/{$form->uuid}", ['attributes' => ['EMAIL' => $email]])->assertOk()
            ->assertJsonPath('data.status', 'pending_confirmation');
    }

    private function confirmResponse(array $extra = [], string $email = 'amina@exemple.dz'): TestResponse
    {
        $url = URL::temporarySignedRoute('preferences.confirm.store', now()->addHour(), ['token' => UnsubscribeUrlGenerator::encode($email)] + $extra);

        return $this->post($url);
    }

    public function test_the_form_template_is_sent_with_the_confirmation_link(): void
    {
        $template = Template::factory()->create([
            'subject' => 'Bienvenue chez nous',
            'html_content' => '<html><body><p>Salut {{ contact.PRENOM | default:"ami" }}</p><a href="{{ doubleoptin }}">Oui</a></body></html>',
        ]);
        $this->submit($this->form($template));

        Mail::assertQueued(DoubleOptInMail::class, function (DoubleOptInMail $mail): bool {
            return $mail->mailSubject === 'Bienvenue chez nous'
                && str_contains((string) $mail->bodyHtml, 'Salut ami')
                && str_contains((string) $mail->bodyHtml, 'href="'.e($mail->confirmationUrl).'"')
                && ! str_contains((string) $mail->bodyHtml, '{{')
                && substr_count((string) $mail->bodyHtml, 'Confirmer mon inscription') === 0;
        });
    }

    public function test_a_template_without_the_tag_gets_a_confirmation_link_appended(): void
    {
        $template = Template::factory()->transactional()->create(['subject' => 'Sujet', 'html_content' => '<html><body><p>Texte libre</p></body></html>']);
        $this->submit($this->form($template));

        Mail::assertQueued(DoubleOptInMail::class, function (DoubleOptInMail $mail): bool {
            $html = (string) $mail->bodyHtml;

            return str_contains($html, 'Texte libre')
                && str_contains($html, 'Confirmer mon inscription')
                && str_contains($html, 'href="'.e($mail->confirmationUrl).'"')
                && str_ends_with($html, '</p></body></html>');
        });
    }

    public function test_an_archived_template_falls_back_to_the_default_email(): void
    {
        $template = Template::factory()->create(['is_archived' => true, 'subject' => 'Archivé', 'html_content' => '<p>{{ doubleoptin }}</p>']);
        $this->submit($this->form($template));

        Mail::assertQueued(DoubleOptInMail::class, fn (DoubleOptInMail $m): bool => ! str_contains((string) $m->bodyHtml, 'Archivé') && $m->mailSubject !== 'Archivé' && str_contains((string) $m->bodyHtml, 'Confirmer mon inscription'));
    }

    public function test_a_missing_template_falls_back_to_the_default_email(): void
    {
        $template = Template::factory()->create();
        $form = $this->form($template);
        $template->delete();
        $this->submit($form->refresh());

        Mail::assertQueued(DoubleOptInMail::class, fn (DoubleOptInMail $m): bool => str_contains((string) $m->bodyHtml, 'Confirmer mon inscription'));
    }

    public function test_confirmation_redirects_to_the_form_url_after_confirming(): void
    {
        $form = $this->form(null, ['doi_redirect_url' => 'https://exemple.dz/merci']);
        $this->submit($form);

        $this->confirmResponse()->assertRedirect('https://exemple.dz/merci');
        $this->assertSame('confirmed', FormSubmission::query()->sole()->status);
        $this->assertSame(1, $form->refresh()->confirmed_count);
    }

    public function test_the_most_recent_submissions_form_wins(): void
    {
        $first = $this->form(null, ['doi_redirect_url' => 'https://a.exemple.dz']);
        $second = $this->form(null, ['doi_redirect_url' => 'https://b.exemple.dz']);
        $this->submit($first);
        $this->submit($second);

        $this->confirmResponse()->assertRedirect('https://b.exemple.dz');
    }

    public function test_without_a_redirect_the_default_page_is_shown(): void
    {
        $this->submit($this->form(null));

        $this->confirmResponse()->assertOk()->assertSee('confirmée');
    }

    public function test_a_tampered_redirect_parameter_is_ignored(): void
    {
        $this->submit($this->form(null));

        $this->confirmResponse(['redirect' => 'https://evil.example', 'redirect_url' => 'https://evil.example'])->assertOk()->assertSee('confirmée');
        // Unsigned extra parameter on a validly signed URL: still no redirect.
        $url = URL::temporarySignedRoute('preferences.confirm.store', now()->addHour(), ['token' => UnsubscribeUrlGenerator::encode('amina@exemple.dz')]);
        $this->post($url.'&redirect=https://evil.example')->assertStatus(403);
    }

    public function test_a_javascript_url_is_rejected_on_save(): void
    {
        $this->actingAs(User::factory()->withRole(RoleName::MarketingManager)->create());
        $form = $this->form(null);

        $this->patchJson("/api/v1/forms/{$form->uuid}", ['settings' => ['doi_redirect_url' => 'javascript:alert(1)']])
            ->assertStatus(422)
            ->assertJsonValidationErrors('settings.doi_redirect_url');
    }
}
