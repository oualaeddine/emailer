<?php

namespace App\Modules\Verification\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * docs/22-email-verification.md §22.2/§22.4. `status` uses the API/UI
 * vocabulary already present in `resources/js/Lib/i18n/fr.ts`
 * (`verification.statusValid`/`statusInvalid`/`statusRisky`/`statusUnknown`)
 * rather than the raw DB `verdict` value — see
 * `App\Modules\Verification\Enums\VerificationVerdict::apiStatus()`.
 *
 * @mixin \App\Modules\Verification\Models\VerificationResult
 */
class VerificationResultResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'email' => $this->email,
            'status' => $this->verdictEnum()->apiStatus(),
            'checks' => [
                'syntax_valid' => $this->syntax_valid,
                'mx_valid' => $this->mx_valid,
                'is_disposable' => $this->is_disposable,
                'is_role_based' => $this->is_role_based,
                'is_catch_all' => $this->is_catch_all,
            ],
            'provider' => $this->provider,
            'checked_at' => $this->checked_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
        ];
    }
}
