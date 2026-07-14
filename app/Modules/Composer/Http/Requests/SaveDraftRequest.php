<?php

namespace App\Modules\Composer\Http\Requests;

use App\Domain\Enums\PermissionName;
use Illuminate\Foundation\Http\FormRequest;

/**
 * docs/29-api-specification.md §29.3 — POST/PATCH /api/v1/drafts.
 */
class SaveDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(PermissionName::ComposerCompose->value) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'subject' => ['sometimes', 'nullable', 'string', 'max:998'],
            'html_body' => ['sometimes', 'nullable', 'string'],
            // Accepts the signature's external uuid (docs/04-database-design.md
            // §4.1) — the controller resolves it to the internal id for storage.
            'signature_id' => ['sometimes', 'nullable', 'string', 'exists:signatures,uuid'],
        ];
    }
}
