<?php

namespace App\Modules\Identity\Models;

use App\Modules\Audit\Models\AuditLog;
use App\Support\Concerns\HasUuid;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * docs/04-database-design.md §4.2 — `users`.
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $email
 * @property int $role_id
 * @property string|null $avatar_path
 * @property bool $is_active
 * @property Carbon|null $last_login_at
 * @property Carbon|null $email_verified_at
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUuid, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
        'avatar_path',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Permission names resolved for this user, memoized for the lifetime of
     * the instance to avoid re-querying on repeated hasPermission() calls
     * within the same request (docs/26-rbac.md §26.5).
     *
     * @var list<string>|null
     */
    private ?array $resolvedPermissionNames = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            // docs/28-security.md §28.1 — the TOTP secret and recovery codes
            // are encrypted at rest; only the confirmation timestamp is plain.
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * docs/28-security.md §28.1 — a proven enrolment: a secret exists and a
     * valid code has been entered at least once. A half-finished setup (secret
     * generated, never confirmed) is deliberately not "enabled".
     */
    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_secret !== null
            && $this->two_factor_confirmed_at !== null;
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Route-model binding resolves by `uuid`, never the internal id
     * (docs/04-database-design.md §4.1).
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return HasMany<AuditLog, $this>
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function hasPermission(string $permissionName): bool
    {
        return in_array($permissionName, $this->permissionNames(), true);
    }

    public function hasAnyPermission(string ...$permissionNames): bool
    {
        return count(array_intersect($permissionNames, $this->permissionNames())) > 0;
    }

    /**
     * @return list<string>
     */
    private function permissionNames(): array
    {
        if ($this->resolvedPermissionNames === null) {
            $this->resolvedPermissionNames = $this->role?->permissions->pluck('name')->all() ?? [];
        }

        return $this->resolvedPermissionNames;
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
