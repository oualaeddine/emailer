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
 * @property \Illuminate\Support\Carbon|null $last_login_at
 * @property \Illuminate\Support\Carbon|null $email_verified_at
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
        ];
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
