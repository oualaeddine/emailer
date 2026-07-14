<?php

namespace App\Modules\Identity\Models;

use Database\Factories\PermissionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * docs/04-database-design.md §4.2 — `permissions`.
 *
 * @property int $id
 * @property string $name
 * @property string $module
 * @property string|null $description
 */
class Permission extends Model
{
    /** @use HasFactory<PermissionFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'module',
        'description',
    ];

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'permission_role');
    }

    protected static function newFactory(): PermissionFactory
    {
        return PermissionFactory::new();
    }
}
