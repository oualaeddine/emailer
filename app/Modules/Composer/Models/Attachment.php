<?php

namespace App\Modules\Composer\Models;

use App\Support\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * docs/04-database-design.md §4.3 — `attachments` (polymorphic).
 *
 * @property int $id
 * @property string $uuid
 * @property string $disk
 * @property string $path
 * @property string $original_filename
 * @property string $mime_type
 * @property int $size_bytes
 */
class Attachment extends Model
{
    use HasUuid;

    public const UPDATED_AT = null;

    protected $fillable = [
        'attachable_type',
        'attachable_id',
        'disk',
        'path',
        'original_filename',
        'mime_type',
        'size_bytes',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }
}
