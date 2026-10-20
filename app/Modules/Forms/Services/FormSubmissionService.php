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
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** docs/45 §45.7.4 — the public submit pipeline. */
class FormSubmissionService
{
    public const SOURCE = 'form';

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

        /** @var array{0: FormSubmission, 1: bool, 2: Recipient} $stored */
        $stored = DB::transaction(function () use ($form, $email, $values, $request, $settings): array {
            $existing = Recipient::query()->where('email', $email)->exists();
            $recipient = $this->recipients->createOrMerge(new CreateRecipientData(email: $email, source: RecipientSource::Form));

            if ($recipient->status === RecipientStatus::Suppressed->value || $recipient->getAttribute('blocklisted_at') !== null) {
                throw ValidationException::withMessages(['email' => ["Cette adresse e-mail ne peut pas être inscrite."]]);
            }

            if ($values !== [] && (! $existing || ($settings['update_existing'] ?? true))) {
                $this->attributes->apply($recipient, $values, self::SOURCE);
            }

            foreach (RecipientList::query()->whereIn('uuid', $form->list_ids ?? [])->get() as $list) {
                $this->lists->add($list, [$recipient->id], self::SOURCE, ['formId' => $form->id]);
            }

            $pending = ! empty($settings['double_opt_in']) && $recipient->getAttribute('opt_in_at') === null;
            if ($pending) {
                DB::table('recipients')->where('id', $recipient->id)->update(['opt_in_source' => 'form_pending']);
            } elseif ($recipient->getAttribute('opt_in_at') === null) {
                DB::table('recipients')->where('id', $recipient->id)->update(['opt_in_at' => now(), 'opt_in_source' => self::SOURCE]);
            }

            $submission = $this->store($form, $recipient, $pending ? 'pending_confirmation' : 'subscribed', $email, $values, $request);
            $form->newQuery()->whereKey($form->id)->update([
                'submissions_count' => DB::raw('submissions_count + 1'), 'last_submission_at' => now(),
            ]);

            return [$submission, $pending, $recipient];
        });

        [$submission, $pending, $recipient] = $stored;

        if ($pending) {
            $this->doubleOptIn->send($recipient->refresh());
        }

        FormSubmitted::dispatch($form->id, $submission->id, $recipient->id, $submission->status);

        return $this->result($form, $submission->status);
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
