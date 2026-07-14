<?php

namespace App\Modules\DeliveryEngine\Models;

use App\Domain\Enums\SmtpHealthStatus;
use App\Support\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * docs/04-database-design.md §4.9 — `smtp_accounts`.
 * `password_encrypted` uses Laravel's built-in `encrypted` cast
 * (docs/28-security.md §28.6) — transparently encrypted at rest, never
 * serialized in plaintext (also hidden from array/JSON output below).
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $provider
 * @property string $host
 * @property int $port
 * @property string $encryption
 * @property string $username
 * @property string $password_encrypted
 * @property string $from_email
 * @property string|null $from_name
 * @property int|null $daily_quota
 * @property int|null $hourly_quota
 * @property int|null $minute_quota
 * @property int $max_concurrent_connections
 * @property int|null $max_messages_per_connection
 * @property int $priority
 * @property int $rotation_weight
 * @property bool $warmup_enabled
 * @property int|null $warmup_schedule_id
 * @property string $health_status
 * @property float $bounce_rate
 * @property float $complaint_rate
 * @property float|null $reputation_score
 * @property bool $is_active
 */
class SmtpAccount extends Model
{
    use HasUuid, SoftDeletes;

    protected $fillable = [
        'name',
        'provider',
        'host',
        'port',
        'encryption',
        'username',
        'password_encrypted',
        'from_email',
        'from_name',
        'daily_quota',
        'hourly_quota',
        'minute_quota',
        'max_concurrent_connections',
        'max_messages_per_connection',
        'priority',
        'rotation_weight',
        'warmup_enabled',
        'warmup_schedule_id',
        'is_active',
    ];

    protected $hidden = ['password_encrypted'];

    protected function casts(): array
    {
        return [
            'password_encrypted' => 'encrypted',
            'daily_quota' => 'integer',
            'hourly_quota' => 'integer',
            'minute_quota' => 'integer',
            'warmup_enabled' => 'boolean',
            'is_active' => 'boolean',
            'bounce_rate' => 'float',
            'complaint_rate' => 'float',
            'reputation_score' => 'float',
            'last_tested_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function isEligibleForRotation(): bool
    {
        return $this->is_active && in_array($this->health_status, [
            SmtpHealthStatus::Healthy->value,
            SmtpHealthStatus::Degraded->value,
        ], true);
    }

    /**
     * @return BelongsTo<WarmupSchedule, $this>
     */
    public function warmupSchedule(): BelongsTo
    {
        return $this->belongsTo(WarmupSchedule::class);
    }

    /**
     * @return HasMany<QuotaLedgerEntry, $this>
     */
    public function quotaLedgerEntries(): HasMany
    {
        return $this->hasMany(QuotaLedgerEntry::class);
    }

    /**
     * @return HasMany<SendAttempt, $this>
     */
    public function sendAttempts(): HasMany
    {
        return $this->hasMany(SendAttempt::class);
    }
}
