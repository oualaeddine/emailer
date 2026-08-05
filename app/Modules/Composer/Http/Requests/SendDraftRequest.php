<?php

namespace App\Modules\Composer\Http\Requests;

use App\Domain\Enums\PermissionName;
use Illuminate\Foundation\Http\FormRequest;

/**
 * docs/29-api-specification.md §29.3 — POST /api/v1/drafts/{draft}/send.
 */
class SendDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(PermissionName::ComposerSend->value) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'recipient_id' => ['required', 'string', 'exists:recipients,uuid'],
        ];
    }
}
