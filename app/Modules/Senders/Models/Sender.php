<?php

namespace App\Modules\Senders\Models;

use App\Support\Concerns\HasUuid;
use Database\Factories\SenderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * docs/45 §45.8 Slot A — `senders`.
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $email
 * @property int|null $domain_id
 * @property string|null $reply_to
 * @property bool $is_default
 * @property string|null $verification_method email_link|domain|system
 * @property \Illuminate\Support\Carbon|null $verified_at
 * @property \Illuminate\Support\Carbon|null $verification_sent_at
 * @property-read SendingDomain|null $domain
 */
class Sender extends Model
{
    /** @use HasFactory<SenderFactory> */
    use HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'name', 'email', 'domain_id', 'reply_to', 'is_default', 'verification_method',
        'verified_at', 'verification_sent_at', 'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'verified_at' => 'datetime',
            'verification_sent_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<SendingDomain, $this>
     */
    public function domain(): BelongsTo
    {
        return $this->belongsTo(SendingDomain::class, 'domain_id');
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    public static function domainOf(string $email): string
    {
        return strtolower(substr(strrchr($email, '@') ?: '@', 1));
    }

    protected static function newFactory(): SenderFactory
    {
        return SenderFactory::new();
    }
}
