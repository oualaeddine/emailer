<?php

namespace App\Modules\Verification\Models;

use App\Modules\Verification\Enums\VerificationVerdict;
use Illuminate\Database\Eloquent\Model;

/**
 * docs/04-database-design.md §4.11 — `verification_results`.
 * Append-only cache/history: a fresh check always inserts a new row
 * (docs/22-email-verification.md §22.6 — "Every check ... is retained ...
 * (append rather than update-in-place)"), never updates one in place, so
 * only `id`/`checked_at` (no `created_at`/`updated_at`) exist on this table.
 *
 * @property int $id
 * @property string $email
 * @property bool $syntax_valid
 * @property bool|null $mx_valid
 * @property bool|null $is_disposable
 * @property bool|null $is_role_based
 * @property bool|null $is_catch_all
 * @property string $verdict
 * @property string|null $provider
 * @property array<string, mixed>|null $raw_response
 * @property \Illuminate\Support\Carbon $checked_at
 * @property \Illuminate\Support\Carbon|null $expires_at
 */
class VerificationResult extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'email',
        'syntax_valid',
        'mx_valid',
        'is_disposable',
        'is_role_based',
        'is_catch_all',
        'verdict',
        'provider',
        'raw_response',
        'checked_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'syntax_valid' => 'boolean',
            'mx_valid' => 'boolean',
            'is_disposable' => 'boolean',
            'is_role_based' => 'boolean',
            'is_catch_all' => 'boolean',
            'raw_response' => 'array',
            'checked_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function verdictEnum(): VerificationVerdict
    {
        return VerificationVerdict::from($this->verdict);
    }
}
