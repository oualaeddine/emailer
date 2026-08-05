<?php

namespace App\Modules\Suppression\Models;

use App\Modules\Identity\Models\User;
use App\Support\Concerns\HasUuid;
use Database\Factories\SuppressionEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * docs/04-database-design.md §4.12 — `suppression_entries`.
 *
 * WP-11 additions (docs/29-api-specification.md §29.1 — "All IDs in
 * URLs/payloads are the `uuid` column, never the internal `bigint` PK"):
 * `uuid`/{@see HasUuid} and {@see HasFactory} were added to this
 * previously-internal-only model so it can be exposed through
 * `SuppressionEntryController`/`SuppressionEntryResource` the same way
 * {@see \App\Modules\Recipients\Models\Recipient} is — see
 * `database/migrations/..._add_uuid_to_suppression_entries_table.php`.
 *
 * @property int $id
 * @property string $uuid
 * @property string $email
 * @property string $reason
 * @property int|null $source_message_id
 * @property string|null $notes
 * @property int|null $added_by
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class SuppressionEntry extends Model
{
    /** @use HasFactory<SuppressionEntryFactory> */
    use HasFactory, HasUuid;

    public $timestamps = false;

    protected $fillable = ['email', 'reason', 'source_message_id', 'notes', 'added_by'];

    /**
     * `$timestamps = false` above (this table has `created_at` only, no
     * `updated_at` — docs/04-database-design.md §4.12) means Eloquent does
     * not automatically treat `created_at` as a date attribute, so it's
     * cast explicitly here for consistent ISO-8601 serialization in
     * {@see \App\Modules\Suppression\Http\Resources\SuppressionEntryResource}.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    protected static function newFactory(): SuppressionEntryFactory
    {
        return SuppressionEntryFactory::new();
    }
}
