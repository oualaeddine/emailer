<?php

namespace App\Modules\DeliveryEngine\Http\Requests;

use App\Domain\Enums\PermissionName;
use App\Domain\Enums\SmtpEncryption;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * docs/29-api-specification.md §29.6 — PATCH /api/v1/smtp-accounts/{uuid}.
 * `password` is optional here — blank/absent means "keep current"
 * (docs/18-smtp-management.md §18.2).
 */
class UpdateSmtpAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(PermissionName::SmtpManageCredentials->value) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:150'],
            'provider' => ['sometimes', 'string', 'max:50'],
            'host' => ['sometimes', 'string', 'max:255'],
            'port' => ['sometimes', 'integer', 'min:1', 'max:65535'],
            'encryption' => ['sometimes', new Enum(SmtpEncryption::class)],
            'username' => ['sometimes', 'string', 'max:255'],
            'password' => ['sometimes', 'nullable', 'string'],
            'from_email' => ['sometimes', 'email', 'max:191'],
            'from_name' => ['sometimes', 'nullable', 'string', 'max:150'],
            'daily_quota' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'hourly_quota' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'minute_quota' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'max_concurrent_connections' => ['sometimes', 'integer', 'min:1'],
            'max_messages_per_connection' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'priority' => ['sometimes', 'integer'],
            'rotation_weight' => ['sometimes', 'integer', 'min:1'],
            'warmup_enabled' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
