<?php

namespace App\Modules\Recipients\Models;

use App\Domain\Enums\RecipientStatus;
use App\Modules\Identity\Models\User;
use App\Modules\PageJaunesIntegration\Models\PageJaunesCompanyCache;
use App\Support\Concerns\HasUuid;
use Database\Factories\RecipientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

    /** @var array<string, bool> connection => recipients.blocklisted_at exists */
    private static array $hasBlocklistedAt = [];

    /**
     * docs/45 §45.8 Slot I — a contact created for an address that already
     * has a suppression entry starts blocklisted (`blocklisted_at` mirror +
     * `status = suppressed`): the SyncRecipientBlocklist listener only runs
     * when the entry itself is created.
     */
    protected static function booted(): void
    {
        static::creating(function (Recipient $recipient): void {
            $email = $recipient->getAttribute('email');
            if (! is_string($email) || $email === '') {
                return;
            }

            $entry = DB::table('suppression_entries')->where('email', $email)->first(['created_at']);
            if ($entry === null) {
                return;
            }

            $recipient->setAttribute('status', RecipientStatus::Suppressed->value);
            if ($recipient->getAttribute('blocklisted_at') === null && self::hasBlocklistedAtColumn($recipient)) {
                $recipient->setAttribute('blocklisted_at', $entry->created_at ?? now());
            }
        });
    }

    private static function hasBlocklistedAtColumn(Recipient $recipient): bool
    {
        $connection = (string) $recipient->getConnectionName() ?: 'default';

        return self::$hasBlocklistedAt[$connection] ??= Schema::connection($recipient->getConnectionName())->hasColumn('recipients', 'blocklisted_at');
    }

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
     * `recipients.created_by` (docs/45 §45.8 Slot J).
     *
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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
