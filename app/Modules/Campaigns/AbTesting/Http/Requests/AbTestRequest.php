<?php

namespace App\Modules\Campaigns\AbTesting\Http\Requests;

use App\Modules\Campaigns\AbTesting\Services\AbTestService;
use App\Modules\Campaigns\Models\AbTestVariant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * docs/45-brevo-api-contracts.md §45.7.2 `AbTestInput`:
 * `{ enabled, config: AbTestConfig, variants: [ {label A|B, subject, preview_text?, html_body?, design_json?} ×2 ] }`.
 * Config and both versions are required when `enabled`; a subject test
 * needs both subjects. Content-test readiness (version B html) is checked
 * at schedule time (`errors.ab_test`).
 */
class AbTestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // policy checked in the controller
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        if (! $this->boolean('enabled')) {
            return [
                'enabled' => ['required', 'boolean'],
                'config' => ['sometimes', 'nullable', 'array'],
                'variants' => ['sometimes', 'nullable', 'array'],
            ];
        }

        $subjectTest = $this->input('config.type') === 'subject';

        return [
            'enabled' => ['required', 'boolean'],
            'config' => ['required', 'array'],
            ...AbTestService::configRules('config.'),
            'variants' => ['required', 'array', 'size:2'],
            'variants.*' => ['array'],
            'variants.*.label' => ['required', 'string', Rule::in(AbTestVariant::LABELS), 'distinct'],
            'variants.*.subject' => [$subjectTest ? 'required' : 'nullable', 'string', 'max:255'],
            'variants.*.preview_text' => ['nullable', 'string', 'max:255'],
            'variants.*.html_body' => ['nullable', 'string'],
            'variants.*.design_json' => ['nullable', 'array'],
        ];
    }
}
