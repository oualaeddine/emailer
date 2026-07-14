<?php

namespace App\Modules\DeliveryEngine\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * docs/04-database-design.md §4.9 — `quota_ledger`.
 *
 * @property int $id
 * @property int $smtp_account_id
 * @property string $window_type
 * @property \Illuminate\Support\Carbon $window_start
 * @property int $sent_count
 */
class QuotaLedgerEntry extends Model
{
    protected $table = 'quota_ledger';

    protected $fillable = ['smtp_account_id', 'window_type', 'window_start', 'sent_count'];

    protected function casts(): array
    {
        return ['window_start' => 'datetime'];
    }

    /**
     * @return BelongsTo<SmtpAccount, $this>
     */
    public function smtpAccount(): BelongsTo
    {
        return $this->belongsTo(SmtpAccount::class);
    }
}
