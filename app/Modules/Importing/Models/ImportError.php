<?php

namespace App\Modules\Importing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * docs/04-database-design.md §4.6 — `import_errors`.
 *
 * @property int $id
 * @property int $import_job_id
 * @property int|null $row_number
 * @property string $error_code
 * @property string $message
 */
class ImportError extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['import_job_id', 'row_number', 'error_code', 'message'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<ImportJob, $this>
     */
    public function importJob(): BelongsTo
    {
        return $this->belongsTo(ImportJob::class);
    }
}
