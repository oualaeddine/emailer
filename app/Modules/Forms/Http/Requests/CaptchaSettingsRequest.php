<?php

namespace App\Modules\Forms\Http\Requests;

use App\Domain\Enums\PermissionName;
use App\Modules\Settings\Services\SettingsService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * PUT /api/v1/forms/captcha-settings — `settings.manage` only. The site key and the secret are
 * required when a provider is chosen; the secret is write-only, so an already stored one
 * satisfies "required" and stays when the field is left empty.
 */
class CaptchaSettingsRequest extends FormRequest
{
    public const PROVIDERS = ['none', 'hcaptcha', 'turnstile'];

    public function authorize(): bool
    {
        return $this->user()?->hasPermission(PermissionName::SettingsManage->value) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'provider' => ['required', 'string', Rule::in(self::PROVIDERS)],
            'site_key' => ['nullable', 'string', 'max:255'],
            'secret' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->input('provider') === 'none' || $validator->errors()->isNotEmpty()) {
                return;
            }
            if (trim((string) $this->input('site_key', '')) === '') {
                $validator->errors()->add('site_key', 'La clé de site est obligatoire.');
            }
            $stored = (string) app(SettingsService::class)->get('forms.captcha_secret', '') !== '';
            if (trim((string) $this->input('secret', '')) === '' && ! $stored) {
                $validator->errors()->add('secret', 'La clé secrète est obligatoire.');
            }
        }];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'provider.required' => 'Choisissez un fournisseur de captcha.',
            'provider.in' => 'Le fournisseur de captcha choisi n’est pas valide.',
            'site_key.max' => 'La clé de site ne doit pas dépasser 255 caractères.',
            'secret.max' => 'La clé secrète ne doit pas dépasser 255 caractères.',
        ];
    }
}
