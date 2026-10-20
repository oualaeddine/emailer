<?php

namespace App\Modules\Forms\Models;

use App\Modules\Identity\Models\User;
use App\Support\Concerns\HasUuid;
use Database\Factories\FormFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * docs/45 §45.8 Slot Q — `forms`.
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $status draft|active|inactive
 * @property list<string> $list_ids list uuids
 * @property list<array<string, mixed>> $fields
 * @property array<string, mixed> $gdpr
 * @property array<string, mixed> $design
 * @property array<string, mixed> $settings
 * @property int|null $doi_template_id
 * @property int $submissions_count
 * @property int $confirmed_count
 * @property \Illuminate\Support\Carbon|null $last_submission_at
 * @property int|null $created_by
 */
class Form extends Model
{
    /** @use HasFactory<FormFactory> */
    use HasFactory, HasUuid, SoftDeletes;

    public const DRAFT = 'draft';

    public const ACTIVE = 'active';

    public const INACTIVE = 'inactive';

    protected $fillable = [
        'name', 'status', 'list_ids', 'fields', 'gdpr', 'design', 'settings', 'doi_template_id',
        'submissions_count', 'confirmed_count', 'last_submission_at', 'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'list_ids' => 'array', 'fields' => 'array', 'gdpr' => 'array', 'design' => 'array',
            'settings' => 'array', 'last_submission_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function isActive(): bool
    {
        return $this->status === self::ACTIVE;
    }

    /** @return HasMany<FormSubmission, $this> */
    public function submissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected static function newFactory(): FormFactory
    {
        return FormFactory::new();
    }
}
