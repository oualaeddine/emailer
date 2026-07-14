<?php

namespace App\Modules\Composer\Models;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * docs/04-database-design.md §4.3 — `draft_versions`.
 *
 * @property int $id
 * @property int $draft_id
 * @property int $version_number
 * @property string|null $html_body
 * @property string|null $subject
 * @property int $created_by
 */
class DraftVersion extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['draft_id', 'version_number', 'html_body', 'subject', 'created_by'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Draft, $this>
     */
    public function draft(): BelongsTo
    {
        return $this->belongsTo(Draft::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
