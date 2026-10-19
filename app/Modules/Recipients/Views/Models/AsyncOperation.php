<?php

namespace App\Modules\Recipients\Views\Models;

use App\Modules\Identity\Models\User;
use App\Support\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * docs/45 §45.3 / §45.8 Slot J — `async_operations`.
 *
 * @property int $id
 * @property string $uuid
 * @property int|null $user_id
 * @property string $type
 * @property string $status
 * @property int $total
 * @property int $processed
 * @property int $failed
 * @property array<string, mixed>|null $payload
 * @property array<string, mixed>|null $result
 * @property string|null $error
 * @property \Illuminate\Support\Carbon|null $completed_at
 */
class AsyncOperation extends Model
{
    use HasUuid;

    public const QUEUED = 'queued';

    public const RUNNING = 'running';

    public const COMPLETED = 'completed';

    public const FAILED = 'failed';

    protected $fillable = ['user_id', 'type', 'status', 'total', 'processed', 'failed', 'payload', 'result', 'error', 'completed_at'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'result' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
