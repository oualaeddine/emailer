<?php

namespace App\Modules\Forms\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

/**
 * docs/45 §45.7.4 `FormSubmission`.
 *
 * @mixin \App\Modules\Forms\Models\FormSubmission
 */
class FormSubmissionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'email' => $this->email,
            'recipient_id' => $this->recipient_id ? DB::table('recipients')->where('id', $this->recipient_id)->value('uuid') : null,
            'status' => $this->status,
            'data' => (object) ($this->data ?? []),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'ip_country' => $this->ip_country,
        ];
    }
}
