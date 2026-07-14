<?php

namespace App\Modules\Recipients\Models;

use App\Support\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * docs/04-database-design.md §4.5 — `segments`.
 *
 * @property int $id
 * @property string $uuid
 * @property int $recipient_list_id
 * @property array<string, mixed> $rules
 * @property \Illuminate\Support\Carbon|null $last_evaluated_at
 * @property int|null $cached_count
 */
class Segment extends Model
{
    use HasUuid;

    protected $fillable = ['recipient_list_id', 'rules', 'last_evaluated_at', 'cached_count'];

    protected function casts(): array
    {
        return [
            'rules' => 'array',
            'last_evaluated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<RecipientList, $this>
     */
    public function recipientList(): BelongsTo
    {
        return $this->belongsTo(RecipientList::class);
    }
}
