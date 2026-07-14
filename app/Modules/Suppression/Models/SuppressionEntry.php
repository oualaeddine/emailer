<?php

namespace App\Modules\Suppression\Models;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * docs/04-database-design.md §4.12 — `suppression_entries`.
 *
 * @property int $id
 * @property string $email
 * @property string $reason
 * @property int|null $source_message_id
 * @property string|null $notes
 * @property int|null $added_by
 */
class SuppressionEntry extends Model
{
    public $timestamps = false;

    protected $fillable = ['email', 'reason', 'source_message_id', 'notes', 'added_by'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
