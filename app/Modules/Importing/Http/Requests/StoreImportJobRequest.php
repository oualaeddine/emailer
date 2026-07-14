<?php

namespace App\Modules\Importing\Http\Requests;

use App\Domain\Enums\PermissionName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use App\Domain\Enums\ImportSourceType;

/**
 * docs/29-api-specification.md §29.4 — POST /api/v1/import-jobs (multipart).
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
        return [
            'source_type' => ['required', new Enum(ImportSourceType::class)],
            'file' => ['required', 'file', 'max:20480', 'mimes:csv,txt,xlsx'],
        ];
    }
}
