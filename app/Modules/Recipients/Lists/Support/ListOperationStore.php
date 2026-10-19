<?php

namespace App\Modules\Recipients\Lists\Support;

use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * AsyncOperation-shaped (§45.3) state for WP-53 long work, kept in the cache
 * until WP-57's `AsyncOperations` contract (table `async_operations`) lands.
 * status_url points at `GET /recipient-lists/operations/{id}`.
 */
class ListOperationStore
{
    private const TTL_HOURS = 24;

    /** @return array<string, mixed> */
    public function start(string $type, ?User $user, int $total = 0): array
    {
        $id = (string) Str::uuid();
        $op = [
            'id' => $id,
            'type' => $type,
            'status' => 'queued',
            'progress' => 0,
            'total' => $total,
            'processed' => 0,
            'failed' => 0,
            'result' => null,
            'error' => null,
            'status_url' => '/api/v1/recipient-lists/operations/'.$id,
            'created_at' => now()->toIso8601String(),
            'completed_at' => null,
            'user_id' => $user?->id,
        ];
        $this->put($op);

        return $op;
    }

    /** @return array<string, mixed>|null */
    public function find(string $id): ?array
    {
        return Cache::get($this->key($id));
    }

    /** @param  array<string, mixed>  $changes */
    public function update(string $id, array $changes): void
    {
        $op = $this->find($id);
        if ($op !== null) {
            $this->put(array_merge($op, $changes));
        }
    }

    /** @param  array<string, mixed>  $result */
    public function complete(string $id, array $result): void
    {
        $op = $this->find($id);
        $this->update($id, [
            'status' => 'completed',
            'progress' => 100,
            'processed' => $op['total'] ?? 0,
            'result' => $result,
            'completed_at' => now()->toIso8601String(),
        ]);
    }

    public function fail(string $id, string $error): void
    {
        $this->update($id, ['status' => 'failed', 'error' => $error, 'completed_at' => now()->toIso8601String()]);
    }

    /**
     * @param  array<string, mixed>  $op
     * @return array<string, mixed>
     */
    public function present(array $op): array
    {
        unset($op['user_id']);

        return $op;
    }

    /** @param  array<string, mixed>  $op */
    private function put(array $op): void
    {
        Cache::put($this->key($op['id']), $op, now()->addHours(self::TTL_HOURS));
    }

    private function key(string $id): string
    {
        return 'list-op:'.$id;
    }
}
