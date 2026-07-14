<?php

namespace App\Modules\DeliveryEngine\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\DeliveryEngine\Models\WarmupSchedule
 */
class WarmupScheduleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'stages' => $this->stages,
        ];
    }
}
