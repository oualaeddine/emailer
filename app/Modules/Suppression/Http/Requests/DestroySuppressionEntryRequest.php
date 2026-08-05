<?php

namespace App\Modules\Suppression\Http\Requests;

use App\Domain\Enums\PermissionName;
use Illuminate\Foundation\Http\FormRequest;

/**
 * docs/29-api-specification.md §29.8 — DELETE
 * /api/v1/suppression-entries/{uuid} "requires confirm flag in body".
 * docs/23-suppression-list.md §23.5 — "Remove from suppression (manual
 * override, requires Administrator permission and a confirmation dialog
 * explaining the deliverability risk, itself audited)"; §23.7 — the
 * removal permission gate is the same for every reason, "though the UI
 * does not technically restrict removal by reason". Enforced here as a
 * declarative validation rule (422 on a missing/false `confirm`) rather
 * than a manual controller check, matching how every other FormRequest in
 * this codebase enforces input contracts.
 */
class DestroySuppressionEntryRequest extends FormRequest
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
            'confirm' => ['required', 'accepted'],
        ];
    }
}
