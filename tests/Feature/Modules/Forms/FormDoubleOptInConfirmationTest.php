<?php

namespace Tests\Feature\Modules\Forms;

use App\Modules\Automations\Models\AutomationRun;
use App\Modules\DeliveryEngine\Jobs\SendEmailJob;
use App\Modules\Forms\Events\ContactOptInConfirmed;
use App\Modules\Forms\Models\Form;
use App\Modules\Forms\Models\FormSubmission;
use App\Modules\Recipients\Lists\Events\RecipientsAddedToList;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Recipients\Models\RecipientList;
use App\Modules\Suppression\Preferences\Mail\DoubleOptInMail;
use App\Modules\Suppression\Preferences\Services\DoubleOptInService;
use App\Modules\Suppression\Support\UnsubscribeUrlGenerator;
use Database\Factories\RecipientListFactory;
use Database\Seeders\ContactAttributeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Modules\Automations\Concerns\BuildsAutomationFixtures;
use Tests\TestCase;

/**
 * docs/45 §45.7.4 / §45.7.5 — with double opt-in nothing reaches the contact
 * before the confirmation link is used; the confirmation applies the pending
 * submissions and starts `form_submitted` automations.
 */
class FormDoubleOptInConfirmationTest extends TestCase
{
    use BuildsAutomationFixtures, RefreshDatabase;

    private RecipientList $formList;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContactAttributeSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        Bus::fake([SendEmailJob::class]);
        Mail::fake();
        $this->formList = RecipientListFactory::new()->create();
    }

    /** @param  array<string, mixed>  $settings */
    private function doiForm(array $settings = []): Form
    {
        return Form::factory()->withSettings(['double_opt_in' => true] + $settings)->create([
            'list_ids' => [$this->formList->uuid],
            'fields' => [
                ['attribute' => 'EMAIL', 'label' => 'Email', 'required' => true],
                ['attribute' => 'PRENOM', 'label' => 'Prénom', 'required' => false],
                ['attribute' => 'NOM', 'label' => 'Nom', 'required' => false],
            ],
        ]);
    }

    /** @param  array<string, string>  $attributes */
    private function submit(Form $form, array $attributes = ['PRENOM' => 'Amina'], string $email = 'amina@exemple.dz'): TestResponse
    {
        return $this->postJson("/f/{$form->uuid}", ['attributes' => ['EMAIL' => $email] + $attributes]);
    }

    private function confirm(string $email = 'amina@exemple.dz'): TestResponse
    {
        $url = URL::temporarySignedRoute('preferences.confirm.store', now()->addHour(), ['token' => UnsubscribeUrlGenerator::encode($email)]);

        return $this->post($url)->assertOk()->assertSee('confirmée');
    }

    private function inFormList(Recipient $recipient): bool
    {
        return DB::table('recipient_list_recipient')
            ->where('recipient_list_id', $this->formList->id)->where('recipient_id', $recipient->id)->exists();
    }

    public function test_pending_submission_does_not_touch_an_existing_contact(): void
    {
        Event::fake([RecipientsAddedToList::class]);
        $contact = Recipient::factory()->create(['email' => 'amina@exemple.dz', 'first_name' => 'Old', 'source' => 'manual']);
        DB::table('recipients')->where('id', $contact->id)->update(['opt_in_at' => now()->subYear(), 'opt_in_source' => 'import']);

        $this->submit($this->doiForm())->assertOk()->assertJsonPath('data.status', 'pending_confirmation');

        $contact->refresh();
        $this->assertSame('Old', $contact->first_name);
        $this->assertSame('import', $contact->opt_in_source);
        $this->assertFalse($this->inFormList($contact));
        $this->assertSame('pending_confirmation', FormSubmission::query()->sole()->status);
        Event::assertNotDispatched(RecipientsAddedToList::class);
        Mail::assertQueued(DoubleOptInMail::class, 1);
    }

    public function test_confirmation_applies_the_submission_and_starts_the_waiting_automation(): void
    {
        $form = $this->doiForm();
        $automation = $this->activeAutomation('form_submitted', ['form_ids' => [$form->uuid]], [$this->step('end')]);

        $this->submit($form)->assertOk();
        $recipient = Recipient::query()->where('email', 'amina@exemple.dz')->sole();
        $this->assertSame(0, AutomationRun::query()->count());
        $this->assertFalse($this->inFormList($recipient));

        $this->confirm();

        $submission = FormSubmission::query()->sole();
        $this->assertSame('confirmed', $submission->status);
        $this->assertNotNull($submission->confirmed_at);
        $this->assertSame(1, $form->refresh()->confirmed_count);
        $recipient->refresh();
        $this->assertTrue($this->inFormList($recipient));
        $this->assertSame('Amina', $recipient->first_name);
        $this->assertNotNull($recipient->opt_in_at);

        $run = AutomationRun::query()->sole();
        $this->assertSame($automation->id, $run->automation_id);
        $this->assertSame($recipient->id, $run->recipient_id);
        $this->assertSame('form_submitted', $run->context['trigger']);
        $this->assertSame($form->uuid, $run->context['form_id']);

        // A second click changes nothing.
        $this->confirm();
        $this->assertSame(1, $form->refresh()->confirmed_count);
        $this->assertSame(1, AutomationRun::query()->count());
    }

    public function test_confirmation_dispatches_one_event_per_form_and_list_additions_only_then(): void
    {
        Event::fake([ContactOptInConfirmed::class, RecipientsAddedToList::class]);
        $form = $this->doiForm();
        $other = $this->doiForm();

        $this->submit($other)->assertOk();
        $this->submit($form, ['PRENOM' => 'Amina'])->assertOk();
        $this->submit($form, ['PRENOM' => 'Aminata'])->assertOk(); // latest submission wins
        Event::assertNotDispatched(RecipientsAddedToList::class);
        Mail::assertQueued(DoubleOptInMail::class, 1);

        $this->confirm();
        $recipient = Recipient::query()->where('email', 'amina@exemple.dz')->sole();

        $this->assertSame(0, FormSubmission::query()->where('status', 'pending_confirmation')->count());
        $this->assertSame(2, $form->refresh()->confirmed_count);
        $this->assertSame(1, $other->refresh()->confirmed_count);
        $this->assertSame('Aminata', $recipient->refresh()->first_name);
        Event::assertDispatchedTimes(ContactOptInConfirmed::class, 2);
        Event::assertDispatched(ContactOptInConfirmed::class, fn (ContactOptInConfirmed $e) => $e->formId === $form->id && $e->recipientId === $recipient->id);
        Event::assertDispatched(ContactOptInConfirmed::class, fn (ContactOptInConfirmed $e) => $e->formId === $other->id && $e->recipientId === $recipient->id);
        Event::assertDispatched(RecipientsAddedToList::class);
    }

    public function test_without_update_existing_confirmation_only_fills_blank_attributes(): void
    {
        $contact = Recipient::factory()->create(['email' => 'amina@exemple.dz', 'first_name' => 'Old', 'last_name' => null]);

        $this->submit($this->doiForm(['update_existing' => false]), ['PRENOM' => 'Amina', 'NOM' => 'Benali'])->assertOk();
        $this->confirm();

        $contact->refresh();
        $this->assertSame('Old', $contact->first_name);
        $this->assertSame('Benali', $contact->last_name);
        $this->assertTrue($this->inFormList($contact));
    }

    public function test_confirmation_mail_has_a_per_address_cooldown(): void
    {
        $form = $this->doiForm();

        $this->submit($form)->assertOk()->assertJsonPath('data.status', 'pending_confirmation');
        $this->submit($form)->assertOk()->assertJsonPath('data.status', 'pending_confirmation');
        Mail::assertQueued(DoubleOptInMail::class, 1);
        $this->assertSame(2, FormSubmission::query()->where('status', 'pending_confirmation')->count());

        $this->travel(11)->minutes();
        $this->submit($form)->assertOk();
        Mail::assertQueued(DoubleOptInMail::class, 2);
    }

    public function test_a_mail_failure_does_not_fail_the_saved_submission(): void
    {
        $this->mock(DoubleOptInService::class)->shouldReceive('send')->once()->andThrow(new \RuntimeException('SMTP down'));

        $this->submit($this->doiForm())->assertOk()->assertJsonPath('data.status', 'pending_confirmation');
        $this->assertSame('pending_confirmation', FormSubmission::query()->sole()->status);
    }
}
