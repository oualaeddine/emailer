<?php

namespace App\Modules\Recipients\Models;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * docs/16-email-history.md §16.2 — `recipient_notes` (schema addendum).
 */
class RecipientNote extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['recipient_id', 'user_id', 'body'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Recipient, $this>
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(Recipient::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
