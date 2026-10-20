<?php

namespace App\Modules\Transactional\Http\Requests;

use App\Domain\Enums\PermissionName;
use App\Modules\Transactional\Services\ApiKeyService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreApiKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission(PermissionName::ApiKeysManage->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:1', 'max:100'],
            'scopes' => ['required', 'array', 'min:1'],
            'scopes.*' => ['string', Rule::in(ApiKeyService::SCOPES)],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }
}
