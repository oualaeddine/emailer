<?php

namespace App\Modules\Recipients\Models;

use App\Modules\Identity\Models\User;
use App\Support\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * docs/04-database-design.md §4.5 — `recipient_lists`.
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string|null $description
 * @property string $type
 */
class RecipientList extends Model
{
    use HasUuid, SoftDeletes;

    protected $fillable = ['name', 'description', 'type', 'created_by'];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsToMany<Recipient, $this>
     */
    public function recipients(): BelongsToMany
    {
        return $this->belongsToMany(Recipient::class, 'recipient_list_recipient')
            ->withPivot('added_at');
    }

    /**
     * @return HasOne<Segment, $this>
     */
    public function segment(): HasOne
    {
        return $this->hasOne(Segment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
