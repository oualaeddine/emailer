<?php

namespace App\Modules\Templates\Models;

use App\Modules\Identity\Models\User;
use App\Support\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * docs/45-brevo-api-contracts.md §45.3 `Folder` (kind `template`) / §45.8 Slot K.
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property int|null $created_by
 */
class TemplateFolder extends Model
{
    use HasUuid;

    protected $fillable = ['name', 'created_by'];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return HasMany<Template, $this>
     */
    public function templates(): HasMany
    {
        return $this->hasMany(Template::class, 'folder_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
