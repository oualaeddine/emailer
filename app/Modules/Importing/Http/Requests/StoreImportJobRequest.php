<?php

namespace App\Modules\Importing\Http\Requests;

use App\Domain\Enums\PermissionName;
use App\Modules\Importing\Support\ImportSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * docs/29-api-specification.md §29.4 — POST /api/v1/import-jobs (multipart).
 * docs/45-brevo-api-contracts.md §45.5.7 — also JSON `paste` and `pagejaunes`.
 */
class StoreImportJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(PermissionName::RecipientsImport->value) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = ['source_type' => ['required', Rule::in(ImportSource::REQUEST_VALUES)]];

        return $rules + match ($this->input('source_type')) {
            'paste' => ['content' => ['required', 'string', 'max:5000000']],
            'pagejaunes' => [
                'external_ids' => ['required', 'array', 'min:1', 'max:5000'],
                'external_ids.*' => ['integer'],
                // Resolved contacts sent by the wizard (company + e-mail) — additive to the contract.
                'contacts' => ['nullable', 'array', 'max:20000'],
                'contacts.*.email' => ['required_with:contacts', 'string', 'max:255'],
                'contacts.*.company_name' => ['nullable', 'string', 'max:255'],
                'contacts.*.external_id' => ['nullable', 'integer'],
            ],
            default => ['file' => ['required', 'file', 'max:20480', 'mimes:csv,txt,xlsx']],
        };
    }
}
