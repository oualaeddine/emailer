<?php

namespace App\Modules\Recipients\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * docs/04-database-design.md §4.5 — `tags`.
 *
 * @property int $id
 * @property string $name
 * @property string $color
 */
class Tag extends Model
{
    protected $fillable = ['name', 'color'];

    /**
     * @return BelongsToMany<Recipient, $this>
     */
    public function recipients(): BelongsToMany
    {
        return $this->belongsToMany(Recipient::class, 'recipient_tag');
    }
}
