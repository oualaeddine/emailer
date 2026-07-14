<?php

namespace App\Modules\Composer\Models;

use App\Modules\Identity\Models\User;
use App\Support\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * docs/04-database-design.md §4.3 — `drafts`.
 *
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property int|null $template_id
 * @property string|null $subject
 * @property string|null $html_body
 * @property int|null $signature_id
 * @property string $status
 */
class Draft extends Model
{
    use HasUuid, SoftDeletes;

    protected $fillable = ['user_id', 'template_id', 'subject', 'html_body', 'signature_id', 'status'];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Signature, $this>
     */
    public function signature(): BelongsTo
    {
        return $this->belongsTo(Signature::class);
    }

    /**
     * @return HasMany<DraftVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(DraftVersion::class)->orderByDesc('version_number');
    }

    /**
     * @return MorphMany<Attachment, $this>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
