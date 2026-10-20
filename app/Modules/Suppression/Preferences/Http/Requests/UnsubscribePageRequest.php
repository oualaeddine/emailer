<?php

namespace App\Modules\Suppression\Preferences\Http\Requests;

use App\Domain\Enums\PermissionName;
use Illuminate\Foundation\Http\FormRequest;

/** docs/45 §45.6.6 — POST / PATCH /unsubscribe-pages. */
class UnsubscribePageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(PermissionName::SettingsManage->value) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $req = $this->isMethod('POST') ? 'present' : 'sometimes';
        $name = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'name' => [$name, 'string', 'max:100'],
            'content' => [$req, 'array'],
            'content.title' => ['sometimes', 'string', 'max:200'],
            'content.body' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'content.button_label' => ['sometimes', 'string', 'max:100'],
            'content.confirmation_title' => ['sometimes', 'string', 'max:200'],
            'content.confirmation_message' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'content.show_reason_survey' => ['sometimes', 'boolean'],
            'content.reasons' => ['sometimes', 'array', 'max:20'],
            'content.reasons.*' => ['string', 'max:255'],
            'content.show_preferences_link' => ['sometimes', 'boolean'],
            'content.resubscribe_enabled' => ['sometimes', 'boolean'],
            'design' => ['sometimes', 'array'],
            ...DesignRules::rules(),
        ];
    }
}
