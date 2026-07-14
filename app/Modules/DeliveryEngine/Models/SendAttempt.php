<?php

namespace App\Modules\DeliveryEngine\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * docs/04-database-design.md §4.9 — `send_attempts`.
 *
 * @property int $id
 * @property int $message_id
 * @property int $smtp_account_id
 * @property int $attempt_number
 * @property string $status
 * @property string|null $provider_response
 * @property string|null $error_code
 * @property \Illuminate\Support\Carbon $attempted_at
 * @property int|null $duration_ms
 * @property bool $is_test
 */
class SendAttempt extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'message_id',
        'smtp_account_id',
        'attempt_number',
        'status',
        'provider_response',
        'error_code',
        'attempted_at',
        'duration_ms',
        'is_test',
    ];

    protected function casts(): array
    {
        return [
            'attempted_at' => 'datetime',
            'is_test' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<SmtpAccount, $this>
     */
    public function smtpAccount(): BelongsTo
    {
        return $this->belongsTo(SmtpAccount::class);
    }
}
