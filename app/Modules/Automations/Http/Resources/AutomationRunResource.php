<?php

namespace App\Modules\Automations\Http\Resources;

use App\Modules\Automations\Models\AutomationRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * docs/45 §45.7.5 — `AutomationRun`.
 *
 * @mixin AutomationRun
 */
class AutomationRunResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var AutomationRun $run */
        $run = $this->resource;

        return [
            'id' => $run->uuid,
            'recipient' => ['id' => $run->recipient?->uuid, 'email' => $run->recipient?->email],
            'status' => $run->status,
            'current_step_id' => $run->currentStep?->uuid,
            'next_run_at' => $run->next_run_at?->toIso8601ZuluString(),
            'started_at' => $run->started_at?->toIso8601ZuluString(),
            'finished_at' => $run->finished_at?->toIso8601ZuluString(),
            'error' => $run->error,
        ];
    }
}
