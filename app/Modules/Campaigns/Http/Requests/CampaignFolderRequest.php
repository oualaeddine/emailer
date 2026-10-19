<?php

namespace App\Modules\Campaigns\Http\Requests;

use App\Domain\Enums\PermissionName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * docs/45-brevo-api-contracts.md §45.3 `FolderInput` (name 1–100, unique per kind).
 */
class CampaignFolderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(PermissionName::CampaignsCreate->value) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $folder = $this->route('folder');

        return [
            'name' => [
                'required', 'string', 'min:1', 'max:100',
                Rule::unique('campaign_folders', 'name')->ignore($folder?->id),
            ],
        ];
    }
}
