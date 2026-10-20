<?php

namespace App\Modules\Suppression\Preferences\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Suppression\Preferences\Models\UpdateProfileForm;
use App\Modules\Suppression\Preferences\Services\DoubleOptInService;
use App\Modules\Suppression\Preferences\Services\PageDefaults;
use App\Modules\Suppression\Preferences\Services\PageViewBuilder;
use App\Modules\Suppression\Preferences\Services\PreferenceService;
use App\Modules\Suppression\Preferences\Services\PreferenceTokenService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;
use Illuminate\Contracts\View\View;

/**
 * docs/45 §45.6.6 — public `web:/preferences/{token}` (+ `/confirm` for the
 * double opt-in link). Routes carry the `signed` middleware.
 */
class PreferencesController extends Controller
{
    public function __construct(
        private readonly PreferenceTokenService $tokens,
        private readonly PreferenceService $preferences,
        private readonly PageViewBuilder $views,
        private readonly DoubleOptInService $doubleOptIn,
    ) {}

    public function show(Request $request, string $token): View|Response
    {
        $recipient = $this->recipientFor($token);
        if ($recipient === null) {
            return $this->invalid();
        }

        $form = $this->formFor($request);

        return view('preferences.page', $this->views->preferencesPage(
            $recipient,
            $form,
            $request->fullUrl(),
            $request->session()->get('preferences_message'),
        ));
    }

    public function update(Request $request, string $token): RedirectResponse|Response
    {
        $recipient = $this->recipientFor($token);
        if ($recipient === null) {
            return $this->invalid();
        }

        $data = $request->validate([
            'attributes' => ['sometimes', 'array'],
            'attributes.*' => ['nullable', 'string', 'max:500'],
            'list_ids' => ['sometimes', 'array'],
            'list_ids.*' => ['string', 'max:64'],
            'unsubscribe_all' => ['sometimes', 'boolean'],
        ]);

        $form = $this->formFor($request);
        $all = (bool) ($data['unsubscribe_all'] ?? false);
        if (! $all) {
            $missing = [];
            foreach ($form?->fields ?? [] as $field) {
                $value = $data['attributes'][$field['attribute']] ?? null;
                if (! empty($field['required']) && trim((string) $value) === '') {
                    $missing['attributes.'.$field['attribute']] = ['Le champ « '.($field['label'] ?? $field['attribute']).' » est obligatoire.'];
                }
            }
            if ($missing !== []) {
                throw \Illuminate\Validation\ValidationException::withMessages($missing);
            }
        }
        $this->preferences->apply($recipient, $form, $data['attributes'] ?? [], $data['list_ids'] ?? [], $all);

        $message = $all
            ? ($this->preferences->defaultPage()?->content['confirmation_message'] ?? PageDefaults::unsubscribeContent()['confirmation_message'])
            : (($form?->content ?? PageDefaults::profileContent())['success_message'] ?? PageDefaults::profileContent()['success_message']);

        return redirect()->to($request->fullUrl())->with('preferences_message', $message);
    }

    /** Double opt-in landing (GET): no state change, a button POSTs to the signed confirm route. */
    public function confirmLanding(Request $request, string $token): View|Response
    {
        $recipient = $this->recipientFor($token);
        if ($recipient === null) {
            return $this->invalid();
        }

        return view('preferences.page', [
            ...$this->views->simple('confirm', 'Confirmer mon inscription', ''),
            'content' => ['title' => 'Confirmer mon inscription', 'button_label' => 'Confirmer mon inscription'],
            'message' => 'Cliquez sur le bouton pour confirmer votre inscription.',
            'action' => URL::temporarySignedRoute(
                'preferences.confirm.store',
                now()->addHour(),
                ['token' => $token],
            ),
        ]);
    }

    /** Double opt-in confirmation (signed POST). */
    public function confirm(Request $request, string $token): View|Response
    {
        $recipient = $this->recipientFor($token);
        if ($recipient === null) {
            return $this->invalid();
        }
        $this->doubleOptIn->confirm($recipient);

        return view('preferences.page', $this->views->simple(
            'confirmed',
            'Inscription confirmée',
            'Merci, votre inscription est confirmée.',
        ));
    }

    private function recipientFor(string $token): ?Recipient
    {
        try {
            $email = $this->tokens->decode($token);
        } catch (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
            return null;
        }

        return Recipient::query()->where('email', $email)->first();
    }

    private function formFor(Request $request): ?UpdateProfileForm
    {
        $uuid = $request->query('form');
        if (is_string($uuid) && $uuid !== '') {
            $form = UpdateProfileForm::query()->where('uuid', $uuid)->first();
            if ($form !== null) {
                return $form;
            }
        }

        return $this->preferences->defaultForm();
    }

    private function invalid(): Response
    {
        return response()->view('preferences.page', $this->views->simple(
            'invalid',
            'Lien invalide',
            'Ce lien est invalide ou a expiré.',
        ), 404);
    }
}
