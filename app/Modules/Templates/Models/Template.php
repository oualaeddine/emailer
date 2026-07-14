<?php

namespace App\Modules\Templates\Models;

use App\Modules\Identity\Models\User;
use App\Support\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * docs/04-database-design.md §4.4 — `templates`.
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string|null $category
 * @property string|null $thumbnail_path
 * @property string $html_content
 * @property bool $is_archived
 * @property int|null $created_by
 */
class Template extends Model
{
    use HasUuid, SoftDeletes;

    protected $fillable = ['name', 'category', 'thumbnail_path', 'html_content', 'is_archived', 'created_by'];

    protected function casts(): array
    {
        return ['is_archived' => 'boolean'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<TemplateVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(TemplateVersion::class)->orderByDesc('version_number');
    }
}
