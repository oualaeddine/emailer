<?php

namespace App\Modules\Recipients\Views\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Recipients\Views\Contracts\AsyncOperations;
use App\Modules\Recipients\Views\Models\AsyncOperation;

/**
 * docs/45 §45.3 / §45.9 — default {@see AsyncOperations} implementation.
 */
class AsyncOperationService implements AsyncOperations
{
    public function start(string $type, ?User $user, array $payload, int $total = 0): AsyncOperation
    {
        return AsyncOperation::query()->create([
            'user_id' => $user?->id,
            'type' => $type,
            'status' => AsyncOperation::QUEUED,
            'total' => $total,
            'payload' => $payload,
        ]);
    }

    public function progress(AsyncOperation $operation, int $processed, int $failed = 0, ?int $total = null): void
    {
        $operation->forceFill(array_filter([
            'status' => AsyncOperation::RUNNING,
            'processed' => $processed,
            'failed' => $failed,
            'total' => $total,
        ], fn ($v) => $v !== null))->save();
    }

    public function complete(AsyncOperation $operation, array $result = []): void
    {
        $operation->forceFill([
            'status' => AsyncOperation::COMPLETED,
            'result' => $result,
            'completed_at' => now(),
        ])->save();
    }

    public function fail(AsyncOperation $operation, string $error): void
    {
        $operation->forceFill([
            'status' => AsyncOperation::FAILED,
            'error' => $error,
            'completed_at' => now(),
        ])->save();
    }
}
