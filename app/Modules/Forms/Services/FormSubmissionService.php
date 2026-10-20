<?php

namespace App\Modules\Forms\Services;

use App\Domain\Enums\RecipientSource;
use App\Domain\Enums\RecipientStatus;
use App\Modules\Forms\Contracts\CaptchaVerifier;
use App\Modules\Forms\Events\FormSubmitted;
use App\Modules\Forms\Models\Form;
use App\Modules\Forms\Models\FormSubmission;
use App\Modules\Recipients\Attributes\Contracts\ContactAttributeService;
use App\Modules\Recipients\DataTransferObjects\CreateRecipientData;
use App\Modules\Recipients\Lists\Contracts\ListMembershipService;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Recipients\Models\RecipientList;
use App\Modules\Recipients\Services\RecipientService;
use App\Modules\Suppression\Preferences\Services\DoubleOptInService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** docs/45 §45.7.4 — the public submit pipeline. */
class FormSubmissionService
{
    public const SOURCE = 'form';

    /** One double opt-in mail per address per this many minutes. */
    public const DOI_COOLDOWN_MINUTES = 10;

    public function __construct(
        private readonly RecipientService $recipients,
        private readonly ContactAttributeService $attributes,
        private readonly ListMembershipService $lists,
        private readonly DoubleOptInService $doubleOptIn,
        private readonly CaptchaVerifier $captcha,
    ) {}

    /**
     * @param  array<string, mixed>  $input  raw request payload
     * @return array{status: string, message: string, redirect_url: string|null}
     *
     * @throws ValidationException
     */
    public function submit(Form $form, array $input, Request $request): array
    {
        $settings = $form->settings ?? [];
        $ip = $request->ip();

        // Honeypot: pretend success, keep a trace, touch nothing else.
        if (trim((string) ($input['website'] ?? '')) !== '') {
            $this->store($form, null, 'spam', strtolower(trim((string) ($input['email'] ?? ''))) ?: 'spam@invalid', [], $request);

            return $this->result($form, 'subscribed');
        }

        if (! empty($settings['captcha_enabled'])) {
            $token = $input['captcha_token'] ?? $input['h-captcha-response'] ?? $input['cf-turnstile-response'] ?? null;
            if (! $this->captcha->verify(is_string($token) ? $token : null, $ip)) {
                throw ValidationException::withMessages(['captcha_token' => ['La vérification anti-robot a échoué. Réessayez.']]);
            }
        }

        [$email, $values] = $this->validated($form, $input);
        $doubleOptIn = ! empty($settings['double_opt_in']);

        // Suppressed / blocklisted addresses get the answer any new address gets (no blocklist
        // enumeration) and nothing is created, stored or mailed.
        if ($this->isBlocked($email)) {
            return $this->result($form, $doubleOptIn ? 'pending_confirmation' : 'subscribed');
        }

        /** @var array{0: FormSubmission, 1: bool, 2: Recipient} $stored */
        $stored = DB::transaction(function () use ($form, $email, $values, $request, $settings, $doubleOptIn): array {
            $existing = Recipient::query()->where('email', $email)->first();
            // With double opt-in every submission waits for the confirmation, also for a contact
            // opted in earlier: otherwise anyone could add that address to the form's lists or
            // overwrite its attributes.
            $pending = $doubleOptIn;

            if ($pending) {
                // Nothing reaches the contact (lists, attributes) before the double opt-in is
                // confirmed (FormConfirmationService): the values wait in `form_submissions.data`.
                $this->assertStorable($values);
                $recipient = $existing ?? $this->recipients->createOrMerge(new CreateRecipientData(email: $email, source: RecipientSource::Form));
                if ($existing === null || ($existing->getAttribute('opt_in_at') === null && $existing->getAttribute('opt_in_source') === null)) {
                    DB::table('recipients')->where('id', $recipient->id)->update(['opt_in_source' => 'form_pending']);
                }
            } else {
                $recipient = $this->recipients->createOrMerge(new CreateRecipientData(email: $email, source: RecipientSource::Form));

                if ($values !== [] && ($existing === null || ($settings['update_existing'] ?? true))) {
                    $this->attributes->apply($recipient, $values, self::SOURCE);
                }

                foreach (RecipientList::query()->whereIn('uuid', $form->list_ids ?? [])->get() as $list) {
                    $this->lists->add($list, [$recipient->id], self::SOURCE, ['formId' => $form->id]);
                }

                if ($recipient->getAttribute('opt_in_at') === null) {
                    DB::table('recipients')->where('id', $recipient->id)->update(['opt_in_at' => now(), 'opt_in_source' => self::SOURCE]);
                }
            }

            $submission = $this->store($form, $recipient, $pending ? 'pending_confirmation' : 'subscribed', $email, $values, $request);
            $form->newQuery()->whereKey($form->id)->update([
                'submissions_count' => DB::raw('submissions_count + 1'), 'last_submission_at' => now(),
            ]);

            return [$submission, $pending, $recipient];
        });

        [$submission, $pending, $recipient] = $stored;

        if ($pending) {
            $this->sendConfirmation($recipient, $form);
        }

        FormSubmitted::dispatch($form->id, $submission->id, $recipient->id, $submission->status);

        return $this->result($form, $submission->status);
    }

    /** Suppression entry, or a suppressed / blocklisted contact. */
    private function isBlocked(string $email): bool
    {
        if (DB::table('suppression_entries')->where('email', $email)->exists()) {
            return true;
        }

        $recipient = Recipient::query()->where('email', $email)->first();

        return $recipient !== null
            && ($recipient->status === RecipientStatus::Suppressed->value || $recipient->getAttribute('blocklisted_at') !== null);
    }

    /**
     * Validates pending values the way ContactAttributeService::apply() would, without writing.
     *
     * @param  array<string, mixed>  $values
     *
     * @throws ValidationException
     */
    private function assertStorable(array $values): void
    {
        $definitions = $this->attributes->definitions();
        foreach ($values as $name => $value) {
            $this->attributes->castForStorage($definitions->get($name), $value);
        }
    }

    /**
     * At most one confirmation mail per address per DOI_COOLDOWN_MINUTES (the submission is
     * still stored and confirmed by any earlier link); queued, and a mail failure never
     * fails a submission already saved.
     */
    private function sendConfirmation(Recipient $recipient, Form $form): void
    {
        $key = 'forms:doi-cooldown:'.hash('sha256', strtolower($recipient->email));
        if (! Cache::add($key, true, now()->addMinutes(self::DOI_COOLDOWN_MINUTES))) {
            return;
        }

        try {
            $this->doubleOptIn->send($recipient->refresh(), $form->doi_template_id);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{0: string, 1: array<string, mixed>} [email, attribute values without EMAIL]
     */
    private function validated(Form $form, array $input): array
    {
        $raw = is_array($input['attributes'] ?? null) ? $input['attributes'] : [];
        $definitions = $this->attributes->definitions();
        $errors = [];
        $values = [];
        $email = strtolower(trim((string) ($raw['EMAIL'] ?? $input['email'] ?? '')));

        foreach ($form->fields as $field) {
            $name = strtoupper((string) $field['attribute']);
            if ($name === 'EMAIL' || ! $definitions->has($name)) {
                continue;
            }
            $value = $raw[$name] ?? null;
            $blank = $value === null || $value === '' || $value === [];
            if ($blank) {
                if (! empty($field['required'])) {
                    $errors["attributes.$name"] = ['Le champ « '.($field['label'] ?? $name).' » est obligatoire.'];
                }

                continue;
            }
            $values[$name] = is_array($value) ? array_values(array_map('strval', $value)) : (string) $value;
        }

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 254) {
            $errors['email'] = ["Saisissez une adresse e-mail valide."];
        }

        $gdpr = $form->gdpr ?? [];
        if (! empty($gdpr['enabled']) && ! empty($gdpr['required']) && ! filter_var($input['gdpr_consent'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $errors['gdpr_consent'] = ['Vous devez accepter les conditions pour vous inscrire.'];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [$email, $values];
    }

    /** @param  array<string, mixed>  $data */
    private function store(Form $form, ?Recipient $recipient, string $status, string $email, array $data, Request $request): FormSubmission
    {
        $country = strtoupper((string) $request->header('CF-IPCountry', ''));

        return FormSubmission::query()->create([
            'form_id' => $form->id,
            'recipient_id' => $recipient?->id,
            'email' => $email,
            'status' => $status,
            'data' => $data,
            'ip_hash' => $request->ip() ? hash('sha256', $request->ip()) : null,
            'ip_country' => preg_match('/^[A-Z]{2}$/', $country) ? $country : null,
            'user_agent' => $request->userAgent() ? mb_substr($request->userAgent(), 0, 512) : null,
            'submitted_at' => now(),
            'confirmed_at' => null,
        ]);
    }

    /** @return array{status: string, message: string, redirect_url: string|null} */
    private function result(Form $form, string $status): array
    {
        $settings = $form->settings ?? [];
        $pending = $status === 'pending_confirmation';

        return [
            'status' => $pending ? 'pending_confirmation' : 'subscribed',
            'message' => $pending
                ? 'Consultez votre boîte de réception pour confirmer votre inscription.'
                : (string) ($settings['success_message'] ?? 'Merci !'),
            'redirect_url' => $settings['redirect_url'] ?? null,
        ];
    }
}
