<?php

namespace App\Modules\Templates\Models;

use App\Modules\Identity\Models\User;
use App\Support\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * docs/04-database-design.md §4.4 — `template_blocks`.
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $block_type
 * @property string $html_content
 * @property int|null $created_by
 */
class TemplateBlock extends Model
{
    use HasUuid;

    protected $fillable = ['name', 'block_type', 'html_content', 'created_by'];

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
}
