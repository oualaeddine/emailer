<?php

namespace App\Modules\Verification\Http\Requests;

use App\Domain\Enums\PermissionName;
use Illuminate\Foundation\Http\FormRequest;

/**
 * docs/22-email-verification.md §22.7/§22.8 — ad hoc bulk-verify action,
 * gated by the same permission as single verify (`recipients.verify`).
 */
class VerifyBulkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(PermissionName::RecipientsVerify->value) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'recipient_uuids' => ['required', 'array', 'min:1'],
            'recipient_uuids.*' => ['string', 'uuid'],
        ];
    }
}
