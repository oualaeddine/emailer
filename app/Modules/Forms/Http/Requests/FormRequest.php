<?php

namespace App\Modules\Forms\Http\Requests;

use App\Modules\Suppression\Preferences\Http\Requests\DesignRules;
use Illuminate\Foundation\Http\FormRequest as BaseRequest;
use Illuminate\Validation\Rule;

/** docs/45 §45.7.4 `SubscriptionFormInput` (partial on PATCH). */
class FormRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage', \App\Modules\Forms\Models\Form::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $req = $this->isMethod('PATCH') ? ['sometimes'] : ['required'];
        $url = ['sometimes', 'nullable', 'string', 'max:500', 'url:http,https'];

        return [
            'name' => [...$req, 'string', 'max:150'],
            'list_ids' => ['sometimes', 'array', 'max:50'],
            'list_ids.*' => ['uuid', Rule::exists('recipient_lists', 'uuid')->whereNull('deleted_at')],
            'fields' => ['sometimes', 'array', 'min:1', 'max:50'],
            'fields.*.attribute' => ['required', 'string', 'max:64', Rule::exists('contact_attributes', 'name')],
            'fields.*.label' => ['sometimes', 'string', 'max:150'],
            'fields.*.placeholder' => ['sometimes', 'nullable', 'string', 'max:150'],
            'fields.*.required' => ['sometimes', 'boolean'],
            'fields.*.help_text' => ['sometimes', 'nullable', 'string', 'max:300'],
            'gdpr' => ['sometimes', 'array'],
            'gdpr.enabled' => ['sometimes', 'boolean'],
            'gdpr.text' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'gdpr.required' => ['sometimes', 'boolean'],
            'design' => ['sometimes', 'array'],
            ...DesignRules::rules(),
            'design.button_text' => ['sometimes', 'string', 'max:60'],
            'design.title' => ['sometimes', 'nullable', 'string', 'max:200'],
            'design.description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'design.layout' => ['sometimes', Rule::in(['vertical', 'horizontal'])],
            'settings' => ['sometimes', 'array'],
            'settings.double_opt_in' => ['sometimes', 'boolean'],
            'settings.doi_template_id' => ['sometimes', 'nullable', 'uuid', Rule::exists('templates', 'uuid')],
            'settings.doi_redirect_url' => $url,
            'settings.success_message' => ['sometimes', 'string', 'max:500'],
            'settings.redirect_url' => $url,
            'settings.captcha_enabled' => ['sometimes', 'boolean'],
            'settings.update_existing' => ['sometimes', 'boolean'],
            'settings.notify_emails' => ['sometimes', 'array', 'max:10'],
            'settings.notify_emails.*' => ['email'],
        ];
    }

    public function messages(): array
    {
        $invalidUrl = 'Saisissez une URL complète commençant par http:// ou https://.';

        return [
            'name.required' => 'Le nom du formulaire est obligatoire.',
            'settings.doi_redirect_url.url' => $invalidUrl,
            'settings.redirect_url.url' => $invalidUrl,
            'settings.doi_template_id.exists' => 'Le modèle d’e-mail de confirmation est introuvable.',
        ];
    }
}
