<?php

namespace App\Modules\Senders\Models;

use App\Support\Concerns\HasUuid;
use Database\Factories\SendingDomainFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * docs/45 §45.8 Slot A — `sending_domains`.
 *
 * @property int $id
 * @property string $uuid
 * @property string $domain
 * @property string $status pending|checking|verified|partially_verified|failed
 * @property string $dkim_selector
 * @property string|null $dkim_private_key
 * @property string|null $dkim_public_key
 * @property array<int, array<string, mixed>> $records
 * @property \Illuminate\Support\Carbon|null $last_checked_at
 */
class SendingDomain extends Model
{
    /** @use HasFactory<SendingDomainFactory> */
    use HasFactory, HasUuid;

    public const STATUS_PENDING = 'pending';

    public const STATUS_CHECKING = 'checking';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_PARTIAL = 'partially_verified';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'domain', 'status', 'dkim_selector', 'dkim_private_key', 'dkim_public_key',
        'records', 'last_checked_at', 'created_by',
    ];

    protected $hidden = ['dkim_private_key'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dkim_private_key' => 'encrypted',
            'records' => 'array',
            'last_checked_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return HasMany<Sender, $this>
     */
    public function senders(): HasMany
    {
        return $this->hasMany(Sender::class, 'domain_id');
    }

    public function recordStatus(string $kind): ?string
    {
        foreach ($this->records ?? [] as $record) {
            if (($record['kind'] ?? null) === $kind) {
                return $record['status'] ?? null;
            }
        }

        return null;
    }

    public function isAuthenticated(): bool
    {
        return $this->recordStatus('spf') === 'valid' && $this->recordStatus('dkim') === 'valid';
    }

    protected static function newFactory(): SendingDomainFactory
    {
        return SendingDomainFactory::new();
    }
}
