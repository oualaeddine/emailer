<?php

namespace App\Modules\Importing\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Recipients\Models\RecipientList;
use App\Support\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * docs/04-database-design.md §4.6 — `import_jobs`.
 *
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property string $source_type
 * @property string|null $file_path
 * @property string|null $original_filename
 * @property string $status
 * @property int $total_rows
 * @property int $processed_rows
 * @property int $imported_count
 * @property int $duplicate_count
 * @property int $error_count
 * @property array<string, mixed>|null $column_mapping
 * @property int|null $target_recipient_list_id
 */
class ImportJob extends Model
{
    use HasUuid;

    protected $fillable = [
        'user_id',
        'source_type',
        'file_path',
        'original_filename',
        'status',
        'total_rows',
        'processed_rows',
        'imported_count',
        'duplicate_count',
        'error_count',
        'column_mapping',
        'target_recipient_list_id',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'column_mapping' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

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
     * @return BelongsTo<RecipientList, $this>
     */
    public function targetRecipientList(): BelongsTo
    {
        return $this->belongsTo(RecipientList::class, 'target_recipient_list_id');
    }

    /**
     * @return HasMany<ImportRow, $this>
     */
    public function rows(): HasMany
    {
        return $this->hasMany(ImportRow::class);
    }

    /**
     * @return HasMany<ImportError, $this>
     */
    public function errors(): HasMany
    {
        return $this->hasMany(ImportError::class);
    }
}
