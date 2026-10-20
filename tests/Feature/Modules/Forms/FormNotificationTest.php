<?php

namespace Tests\Feature\Modules\Forms;

use App\Modules\Forms\Mail\FormSubmissionNotificationMail;
use App\Modules\Forms\Models\Form;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Suppression\Models\SuppressionEntry;
use App\Modules\Suppression\Preferences\Mail\DoubleOptInMail;
use Database\Factories\RecipientListFactory;
use Database\Seeders\ContactAttributeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** gap-forms — `settings.notify_emails`: one queued notification per address after a public submission. */
class FormNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContactAttributeSeeder::class);
        Mail::fake();
    }

    /** @param  array<string, mixed>  $settings */
    private function form(array $settings): Form
    {
        return Form::factory()->withSettings($settings)->create([
            'name' => 'Newsletter <b>été</b>',
            'list_ids' => [RecipientListFactory::new()->create()->uuid],
            'fields' => [
                ['attribute' => 'EMAIL', 'label' => 'Email', 'required' => true],
                ['attribute' => 'PRENOM', 'label' => 'Prénom', 'required' => false],
            ],
        ]);
    }

    /** @param  array<string, mixed>  $attributes */
    private function submit(Form $form, array $attributes = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson("/f/{$form->uuid}", ['attributes' => $attributes + ['EMAIL' => 'amina@exemple.dz', 'PRENOM' => 'Amina <script>x</script>']]);
    }

    public function test_each_address_gets_a_queued_notification_with_the_submission(): void
    {
        $form = $this->form(['double_opt_in' => false, 'notify_emails' => ['a@equipe.dz', 'B@equipe.dz', 'a@equipe.dz']]);

        $this->submit($form)->assertOk()->assertJsonPath('data.status', 'subscribed');

        Mail::assertQueued(FormSubmissionNotificationMail::class, 2);
        Mail::assertQueued(FormSubmissionNotificationMail::class, fn (FormSubmissionNotificationMail $m) => $m->hasTo('a@equipe.dz'));
        Mail::assertQueued(FormSubmissionNotificationMail::class, fn (FormSubmissionNotificationMail $m) => $m->hasTo('b@equipe.dz'));

        $recipient = Recipient::query()->where('email', 'amina@exemple.dz')->firstOrFail();
        Mail::assertQueued(FormSubmissionNotificationMail::class, function (FormSubmissionNotificationMail $m) use ($recipient): bool {
            $html = $m->render();

            return $m->contactStatus === 'created'
                && $m->hasSubject('Nouvelle inscription : Newsletter <b>été</b>')
                && str_contains($html, 'Contact créé')
                && str_contains($html, 'amina@exemple.dz')
                && str_contains($html, 'Prénom')
                && str_contains($html, 'Amina &lt;script&gt;x&lt;/script&gt;')
                && ! str_contains($html, '<script>')
                && str_contains($html, '« Newsletter &lt;b&gt;été&lt;/b&gt; »')
                && str_contains($html, rtrim((string) config('app.url'), '/').'/contacts/'.$recipient->uuid);
        });
    }

    public function test_an_existing_contact_is_reported_as_updated(): void
    {
        Recipient::factory()->create(['email' => 'amina@exemple.dz']);
        $form = $this->form(['double_opt_in' => false, 'notify_emails' => ['a@equipe.dz']]);

        $this->submit($form)->assertOk();

        Mail::assertQueued(FormSubmissionNotificationMail::class, fn (FormSubmissionNotificationMail $m) => $m->contactStatus === 'updated'
            && str_contains($m->render(), 'Contact mis à jour'));
    }

    public function test_double_opt_in_notifies_on_submission_with_the_pending_state(): void
    {
        $form = $this->form(['double_opt_in' => true, 'notify_emails' => ['a@equipe.dz']]);

        $this->submit($form)->assertOk()->assertJsonPath('data.status', 'pending_confirmation');

        Mail::assertQueued(DoubleOptInMail::class, 1);
        Mail::assertQueued(FormSubmissionNotificationMail::class, fn (FormSubmissionNotificationMail $m) => $m->contactStatus === 'pending_confirmation'
            && str_contains($m->render(), 'En attente de confirmation'));
    }

    public function test_nothing_is_sent_without_addresses(): void
    {
        $this->submit($this->form(['double_opt_in' => false, 'notify_emails' => []]))->assertOk();

        Mail::assertNotQueued(FormSubmissionNotificationMail::class);
    }

    public function test_nothing_is_sent_for_a_blocklisted_address_or_the_honeypot(): void
    {
        $form = $this->form(['double_opt_in' => false, 'notify_emails' => ['a@equipe.dz']]);
        SuppressionEntry::factory()->create(['email' => 'amina@exemple.dz']);

        $this->submit($form)->assertOk();
        $this->postJson("/f/{$form->uuid}", ['website' => 'spam', 'attributes' => ['EMAIL' => 'x@exemple.dz']])->assertOk();

        Mail::assertNothingQueued();
    }

    public function test_the_submission_succeeds_when_queueing_the_notification_throws(): void
    {
        $this->app->instance('mail.manager', new class($this->app) extends \Illuminate\Mail\MailManager
        {
            public function to($users, $name = null)
            {
                throw new \RuntimeException('smtp down');
            }
        });
        \Illuminate\Support\Facades\Mail::clearResolvedInstances();
        \Illuminate\Support\Facades\Log::spy();

        $form = $this->form(['double_opt_in' => false, 'notify_emails' => ['a@equipe.dz']]);
        $this->submit($form)->assertOk()->assertJsonPath('data.status', 'subscribed');

        $this->assertSame(1, Recipient::query()->where('email', 'amina@exemple.dz')->count());
        \Illuminate\Support\Facades\Log::shouldHaveReceived('warning')->once();
    }
}
