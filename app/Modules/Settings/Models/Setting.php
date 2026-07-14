<?php

namespace App\Modules\Settings\Models;

use App\Domain\Enums\SettingValueType;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

/**
 * docs/04-database-design.md §4.15 — `settings`.
 *
 * Secret values (`is_secret = true`) are stored encrypted inside the
 * `value` JSON column (docs/28-security.md §28.6) — encryption is applied
 * in the accessor/mutator here rather than a static Eloquent cast, since
 * whether to encrypt depends on another column (`is_secret`) on the same
 * row, which a plain attribute cast cannot express.
 *
 * @property int $id
 * @property string $key
 * @property mixed $value
 * @property string $value_type
 * @property bool $is_secret
 * @property int|null $updated_by
 */
class Setting extends Model
{
    public const CREATED_AT = null;

    protected $fillable = [
        'key',
        'value',
        'value_type',
        'is_secret',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_secret' => 'boolean',
            'updated_at' => 'datetime',
        ];
    }

    public function value(): mixed
    {
        $raw = $this->getRawOriginal('value');

        if ($raw === null) {
            return null;
        }

        $decoded = json_decode($raw, true);

        if ($this->is_secret && is_string($decoded)) {
            $decoded = Crypt::decryptString($decoded);
        }

        return $this->castValue($decoded);
    }

    public function setValue(mixed $value): void
    {
        $stored = $this->is_secret && is_string($value) ? Crypt::encryptString($value) : $value;

        $this->attributes['value'] = json_encode($stored);
    }

    private function castValue(mixed $decoded): mixed
    {
        return match (SettingValueType::from($this->value_type)) {
            SettingValueType::String => (string) $decoded,
            SettingValueType::Int => (int) $decoded,
            SettingValueType::Bool => (bool) $decoded,
            SettingValueType::Json => $decoded,
        };
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
