<?php

namespace App\Modules\Recipients\Models;

use App\Modules\PageJaunesIntegration\Models\PageJaunesCompanyCache;
use App\Support\Concerns\HasUuid;
use Database\Factories\RecipientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

/**
 * docs/04-database-design.md §4.5 — `recipients`.
 *
 * @property int $id
 * @property string $uuid
 * @property string $email
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $company_name
 * @property int|null $pagejaunes_company_cache_id
 * @property string $source
 * @property string $status
 * @property array<string, mixed>|null $custom_fields
 * @property \Illuminate\Support\Carbon|null $last_contacted_at
 */
class Recipient extends Model
{
    /** @use HasFactory<RecipientFactory> */
    use HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'email',
        'first_name',
        'last_name',
        'company_name',
        'pagejaunes_company_cache_id',
        'source',
        'status',
        'custom_fields',
        'last_contacted_at',
    ];

    protected function casts(): array
    {
        return [
            'custom_fields' => 'array',
            'last_contacted_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<PageJaunesCompanyCache, $this>
     */
    public function pageJaunesCompany(): BelongsTo
    {
        return $this->belongsTo(PageJaunesCompanyCache::class, 'pagejaunes_company_cache_id');
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'recipient_tag');
    }

    /**
     * @return BelongsToMany<RecipientList, $this>
     */
    public function recipientLists(): BelongsToMany
    {
        return $this->belongsToMany(RecipientList::class, 'recipient_list_recipient')
            ->withPivot('added_at');
    }

    /**
     * @return HasMany<RecipientNote, $this>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(RecipientNote::class);
    }

    protected static function newFactory(): RecipientFactory
    {
        return RecipientFactory::new();
    }
}
