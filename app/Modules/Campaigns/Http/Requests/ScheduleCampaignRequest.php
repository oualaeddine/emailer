<?php

namespace App\Modules\Campaigns\Http\Requests;

use App\Domain\Enums\PermissionName;
use Illuminate\Foundation\Http\FormRequest;

/**
 * docs/15-campaign-management.md §15.3 — Scheduling: a single future
 * `scheduled_at`. Scheduling starts the same dispatch chain as sending
 * immediately (just deferred), so it is gated by the same base
 * `campaigns.send`/`campaigns.send_any` permissions here; the precise
 * own-vs-any `CampaignPolicy::send` check runs in the controller via
 * `$this->authorize()`.
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
        return [
            'scheduled_at' => ['required', 'date', 'after:now'],
        ];
    }
}
