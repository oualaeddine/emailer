<?php

namespace App\Modules\Recipients\Views\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Recipients\Models\RecipientList;
use App\Modules\Recipients\Models\Segment;
use App\Support\Concerns\HasUuid;
use Database\Factories\ContactViewFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * docs/45 §45.5.4 / §45.8 Slot J — `contact_views`.
 *
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property string $name
 * @property array<string, mixed>|null $filters
 * @property int|null $list_id
 * @property int|null $segment_id
 * @property list<array{key: string, width?: int}> $columns
 * @property string|null $sort
 * @property bool $is_shared
 */
class ContactView extends Model
{
    /** @use HasFactory<ContactViewFactory> */
    use HasFactory, HasUuid;

    protected $fillable = ['user_id', 'name', 'filters', 'list_id', 'segment_id', 'columns', 'sort', 'is_shared', 'position'];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'columns' => 'array',
            'is_shared' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<RecipientList, $this> */
    public function list(): BelongsTo
    {
        return $this->belongsTo(RecipientList::class, 'list_id');
    }

    /** @return BelongsTo<Segment, $this> */
    public function segment(): BelongsTo
    {
        return $this->belongsTo(Segment::class, 'segment_id');
    }

    protected static function newFactory(): ContactViewFactory
    {
        return ContactViewFactory::new();
    }
}
