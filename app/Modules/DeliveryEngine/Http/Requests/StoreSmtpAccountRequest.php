<?php

namespace App\Modules\DeliveryEngine\Http\Requests;

use App\Domain\Enums\PermissionName;
use App\Domain\Enums\SmtpEncryption;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * docs/29-api-specification.md §29.6 — POST /api/v1/smtp-accounts.
 */
class StoreSmtpAccountRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:150'],
            'provider' => ['required', 'string', 'max:50'],
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'encryption' => ['required', new Enum(SmtpEncryption::class)],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'from_email' => ['required', 'email', 'max:191'],
            'from_name' => ['nullable', 'string', 'max:150'],
            'daily_quota' => ['nullable', 'integer', 'min:1'],
            'hourly_quota' => ['nullable', 'integer', 'min:1'],
            'minute_quota' => ['nullable', 'integer', 'min:1'],
            'max_concurrent_connections' => ['sometimes', 'integer', 'min:1'],
            'max_messages_per_connection' => ['nullable', 'integer', 'min:1'],
            'priority' => ['sometimes', 'integer'],
            'rotation_weight' => ['sometimes', 'integer', 'min:1'],
            'warmup_enabled' => ['sometimes', 'boolean'],
        ];
    }
}
