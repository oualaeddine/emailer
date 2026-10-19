<?php

namespace App\Modules\Campaigns\Http\Requests;

use App\Domain\Enums\PermissionName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * docs/45-brevo-api-contracts.md §45.4.2 `CampaignBulkInput`.
 */
class CampaignBulkRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null || ! $user->hasPermission(PermissionName::CampaignsCreate->value)) {
            return false;
        }

        if (in_array($this->input('action'), ['archive', 'unarchive'], true)) {
            return $user->hasPermission(PermissionName::CampaignsArchive->value);
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['archive', 'unarchive', 'move', 'delete'])],
            'ids' => ['required', 'array', 'min:1', 'max:1000'],
            'ids.*' => ['string'],
            'folder_id' => ['nullable', 'string', 'exists:campaign_folders,uuid'],
        ];
    }
}
