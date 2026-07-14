<?php

namespace App\Modules\Templates\Models;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * docs/04-database-design.md §4.4 — `template_versions`.
 *
 * @property int $id
 * @property int $template_id
 * @property int $version_number
 * @property string $html_content
 * @property int|null $created_by
 * @property string|null $change_note
 */
class TemplateVersion extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['template_id', 'version_number', 'html_content', 'created_by', 'change_note'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Template, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
