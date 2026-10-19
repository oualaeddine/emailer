<?php

namespace App\Modules\Recipients\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Recipients\Segments\Policies\SegmentPolicy;
use App\Support\Concerns\HasUuid;
use Database\Factories\SegmentFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * docs/04-database-design.md §4.5 — `segments`, made standalone by WP-54
 * (docs/45-brevo-api-contracts.md §45.5.2, §45.8 Slot G): own name,
 * description and folder; `recipient_list_id` is a nullable legacy link to
 * the old `dynamic_segment` list (§45.10).
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string|null $description
 * @property int|null $folder_id
 * @property int|null $recipient_list_id
 * @property array<string, mixed> $rules
 * @property int $rules_version
 * @property int|null $contact_count
 * @property Carbon|null $count_computed_at
 * @property Carbon|null $last_evaluated_at
 * @property int|null $cached_count
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[UseFactory(SegmentFactory::class)]
#[UsePolicy(SegmentPolicy::class)]
class Segment extends Model
{
    /** @use HasFactory<SegmentFactory> */
    use HasFactory, HasUuid;

    protected $fillable = [
        'name', 'description', 'folder_id', 'recipient_list_id', 'rules', 'rules_version',
        'contact_count', 'count_computed_at', 'last_evaluated_at', 'cached_count', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'rules' => 'array',
            'rules_version' => 'integer',
            'contact_count' => 'integer',
            'cached_count' => 'integer',
            'last_evaluated_at' => 'datetime',
            'count_computed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Legacy writers (RecipientListService::createDynamicSegment) create a
        // segment without a name: inherit the owning list's name (Slot G backfill rule).
        static::creating(function (Segment $segment): void {
            if (blank($segment->name)) {
                $listName = $segment->recipient_list_id !== null
                    ? RecipientList::withTrashed()->whereKey($segment->recipient_list_id)->value('name')
                    : null;
                $segment->name = $listName ?? 'Segment sans nom';
            }
            if ($segment->rules_version === null) {
                $segment->rules_version = is_array($segment->rules) && isset($segment->rules['version']) ? 2 : 1;
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<RecipientList, $this>
     */
    public function recipientList(): BelongsTo
    {
        return $this->belongsTo(RecipientList::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
