<?php

namespace App\Modules\Recipients\Lists\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Recipients\Models\RecipientList;
use App\Support\Concerns\HasUuid;
use Database\Factories\ListFolderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * docs/45-brevo-api-contracts.md §45.3 `Folder` (kind `list`|`segment`) / §45.8 Slot F.
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $kind
 * @property int|null $created_by
 */
class ListFolder extends Model
{
    /** @use HasFactory<ListFolderFactory> */
    use HasFactory, HasUuid;

    public const KIND_LIST = 'list';

    public const KIND_SEGMENT = 'segment';

    protected $fillable = ['name', 'kind', 'created_by'];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return HasMany<RecipientList, $this>
     */
    public function lists(): HasMany
    {
        return $this->hasMany(RecipientList::class, 'folder_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected static function newFactory(): ListFolderFactory
    {
        return ListFolderFactory::new();
    }
}
