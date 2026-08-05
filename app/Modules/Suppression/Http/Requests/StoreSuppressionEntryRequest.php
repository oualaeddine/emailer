<?php

namespace App\Modules\Suppression\Http\Requests;

use App\Domain\Enums\PermissionName;
use App\Domain\Enums\SuppressionReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * docs/29-api-specification.md §29.8 — POST /api/v1/suppression-entries
 * (manual block). docs/23-suppression-list.md §23.2 — `reason` is
 * validated against the full documented taxonomy (not hardcoded to
 * `manual_block`), since manual/administrative suppression legitimately
 * covers other reasons too — e.g. §23.5's "Bulk import: upload a list of
 * addresses to pre-suppress, e.g. known-bad from another system" plausibly
 * wants `invalid_address`, not just `manual_block`. §23.7 — `notes` is a
 * free-text field "encouraged" for `manual_block` specifically, but
 * accepted here for any reason.
 */
class StoreSuppressionEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(PermissionName::SuppressionManage->value) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'reason' => ['required', new Enum(SuppressionReason::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
