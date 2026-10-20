<?php

namespace App\Modules\Recipients\Views\Http\Resources;

use App\Modules\Recipients\Views\Models\AsyncOperation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * docs/45 §45.3 AsyncOperation.
 *
 * @mixin AsyncOperation
 */
class AsyncOperationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $progress = $this->status === AsyncOperation::COMPLETED
            ? 100
            : ($this->total > 0 ? (int) min(99, floor($this->processed / $this->total * 100)) : 0);

        return [
            'id' => $this->uuid,
            'type' => $this->type,
            'status' => $this->status,
            'progress' => $progress,
            'total' => $this->total,
            'processed' => $this->processed,
            'failed' => $this->failed,
            'result' => $this->result,
            'error' => $this->error,
            'status_url' => '/api/v1/operations/'.$this->uuid,
            'created_at' => $this->created_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
        ];
    }
}
