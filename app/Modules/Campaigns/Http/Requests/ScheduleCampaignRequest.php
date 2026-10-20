<?php

namespace App\Modules\Campaigns\Http\Requests;

use App\Domain\Enums\PermissionName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * docs/45-brevo-api-contracts.md §45.4.2 `CampaignScheduleInput` — the four
 * delivery options of "Confirmer l'envoi". Without `mode` the legacy
 * payload (`scheduled_at` only) means `scheduled`.
 */
class ScheduleCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyPermission(
            PermissionName::CampaignsSend->value,
            PermissionName::CampaignsSendAny->value,
        ) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $mode = $this->input('mode', 'scheduled');

        return [
            'mode' => ['sometimes', Rule::in(['now', 'scheduled', 'best_time', 'batches'])],
            'scheduled_at' => [$mode === 'scheduled' ? 'required' : 'nullable', 'date', 'after:now'],
            'timezone' => ['sometimes', 'nullable', 'timezone:all'],
            'batch_size' => [$mode === 'batches' ? 'required' : 'nullable', 'integer', 'between:50,100000'],
            'batch_interval_minutes' => [$mode === 'batches' ? 'required' : 'nullable', 'integer', 'between:5,1440'],
        ];
    }

    /**
     * @return array{mode: string, scheduled_at: string|null, timezone: string|null, batch_size: int|null, batch_interval_minutes: int|null}
     */
    public function options(): array
    {
        return [
            'mode' => $this->validated('mode') ?? 'scheduled',
            'scheduled_at' => $this->validated('scheduled_at'),
            'timezone' => $this->validated('timezone'),
            'batch_size' => $this->validated('batch_size') !== null ? (int) $this->validated('batch_size') : null,
            'batch_interval_minutes' => $this->validated('batch_interval_minutes') !== null ? (int) $this->validated('batch_interval_minutes') : null,
        ];
    }
}
