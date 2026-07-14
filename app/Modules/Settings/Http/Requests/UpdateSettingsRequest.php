<?php

namespace App\Modules\Settings\Http\Requests;

use App\Domain\Enums\BrandingSettingKey;
use App\Domain\Enums\PermissionName;
use Illuminate\Foundation\Http\FormRequest;

/**
 * docs/29-api-specification.md §29.11 — PATCH /api/v1/settings.
 * docs/26-rbac.md §26.3 — `settings.manage` may write any key;
 * `settings.branding_only` may only write the branding subset.
 */
class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        if ($user->hasPermission(PermissionName::SettingsManage->value)) {
            return true;
        }

        if (! $user->hasPermission(PermissionName::SettingsBrandingOnly->value)) {
            return false;
        }

        $submittedKeys = array_keys($this->input('settings', []));
        $allowedKeys = BrandingSettingKey::values();

        return count(array_diff($submittedKeys, $allowedKeys)) === 0;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'settings' => ['required', 'array', 'min:1'],
            'settings.*' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
