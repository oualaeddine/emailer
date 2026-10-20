<?php

namespace App\Modules\Campaigns\Models;

use App\Modules\Identity\Models\User;
use App\Support\Concerns\HasUuid;
use Database\Factories\CampaignFolderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * docs/45-brevo-api-contracts.md §45.3 `Folder` (kind `campaign`) / §45.8 Slot B.
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property int|null $created_by
 */
class CampaignFolder extends Model
{
    /** @use HasFactory<CampaignFolderFactory> */
    use HasFactory, HasUuid;

    protected $fillable = ['name', 'created_by'];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return HasMany<Campaign, $this>
     */
    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class, 'folder_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected static function newFactory(): CampaignFolderFactory
    {
        return CampaignFolderFactory::new();
    }
}
