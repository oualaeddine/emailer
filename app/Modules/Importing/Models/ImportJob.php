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
 * @property list<array<string, mixed>>|null $mapping
 * @property list<string>|null $list_ids
 * @property array<string, mixed>|null $new_list
 * @property bool $update_existing
 * @property bool $empty_values_overwrite
 * @property bool $mark_blocklisted
 * @property bool $mark_opt_in
 * @property array<string, int>|null $summary
 * @property int|null $created_list_id
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
        'mapping',
        'list_ids',
        'new_list',
        'update_existing',
        'empty_values_overwrite',
        'mark_blocklisted',
        'mark_opt_in',
        'summary',
        'created_list_id',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'column_mapping' => 'array',
            'mapping' => 'array',
            'list_ids' => 'array',
            'new_list' => 'array',
            'update_existing' => 'boolean',
            'empty_values_overwrite' => 'boolean',
            'mark_blocklisted' => 'boolean',
            'mark_opt_in' => 'boolean',
            'summary' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** True once the Brevo-style wizard mapping (§45.5.7) has been saved. */
    public function isWizardJob(): bool
    {
        return $this->mapping !== null;
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
