<?php

namespace App\Modules\Importing\Models;

use App\Modules\Recipients\Models\Recipient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * docs/04-database-design.md §4.6 — `import_rows`.
 *
 * @property int $id
 * @property int $import_job_id
 * @property int $row_number
 * @property array<string, mixed> $raw_data
 * @property string|null $parsed_email
 * @property string $validation_status
 * @property int|null $created_recipient_id
 */
class ImportRow extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'import_job_id',
        'row_number',
        'raw_data',
        'parsed_email',
        'validation_status',
        'created_recipient_id',
    ];

    protected function casts(): array
    {
        return ['raw_data' => 'array'];
    }

    /**
     * @return BelongsTo<ImportJob, $this>
     */
    public function importJob(): BelongsTo
    {
        return $this->belongsTo(ImportJob::class);
    }

    /**
     * @return BelongsTo<Recipient, $this>
     */
    public function createdRecipient(): BelongsTo
    {
        return $this->belongsTo(Recipient::class, 'created_recipient_id');
    }
}
