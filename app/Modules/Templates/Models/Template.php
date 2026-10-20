<?php

namespace App\Modules\Templates\Models;

use App\Modules\Identity\Models\User;
use App\Support\Concerns\HasUuid;
use Database\Factories\TemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
 * @property string $editor_type
 * @property array<string, mixed>|null $design_json
 * @property int|null $folder_id
 * @property string $scope
 * @property bool $is_gallery
 * @property string|null $gallery_category
 * @property string|null $subject
 */
class Template extends Model
{
    /** @use HasFactory<TemplateFactory> */
    use HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'name', 'category', 'thumbnail_path', 'html_content', 'is_archived', 'created_by',
        'editor_type', 'design_json', 'folder_id', 'scope', 'is_gallery', 'gallery_category', 'subject',
    ];

    protected function casts(): array
    {
        return ['is_archived' => 'boolean', 'is_gallery' => 'boolean', 'design_json' => 'array'];
    }

    protected static function newFactory(): TemplateFactory
    {
        return TemplateFactory::new();
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
     * @return BelongsTo<TemplateFolder, $this>
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(TemplateFolder::class, 'folder_id');
    }

    /**
     * @return HasMany<TemplateVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(TemplateVersion::class)->orderByDesc('version_number');
    }
}
