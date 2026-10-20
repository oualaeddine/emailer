<?php

namespace App\Modules\Templates\Sections\Models;

use App\Modules\Identity\Models\User;
use App\Support\Concerns\HasUuid;
use Database\Factories\SavedSectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * docs/45-brevo-api-contracts.md §45.7.1 `SavedSection` / §45.8 Slot N —
 * a whole email section (`<mj-section>` or `<mj-wrapper>`) saved from the
 * EmailBuilder, listed under *Contenu › Enregistrés* for every user.
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $mjml
 * @property array<string, mixed> $component
 * @property string|null $thumbnail_path
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SavedSection extends Model
{
    /** @use HasFactory<SavedSectionFactory> */
    use HasFactory, HasUuid;

    protected $fillable = ['name', 'mjml', 'component', 'thumbnail_path', 'created_by'];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'component' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected static function newFactory(): SavedSectionFactory
    {
        return SavedSectionFactory::new();
    }
}
