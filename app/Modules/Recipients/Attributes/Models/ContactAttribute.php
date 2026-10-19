<?php

namespace App\Modules\Recipients\Attributes\Models;

use App\Support\Concerns\HasUuid;
use Database\Factories\ContactAttributeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * docs/45 §45.8 Slot D — `contact_attributes`.
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string|null $label
 * @property string $type
 * @property list<array{value: string, label: string}>|null $options
 * @property bool $is_system
 * @property string $storage
 * @property string|null $column
 * @property int $position
 * @property int|null $created_by
 */
class ContactAttribute extends Model
{
    /** @use HasFactory<ContactAttributeFactory> */
    use HasFactory, HasUuid;

    public const TYPES = ['text', 'number', 'date', 'boolean', 'category', 'multiple_choice', 'user'];

    public const MAX_PER_ACCOUNT = 200;

    protected $fillable = [
        'name', 'label', 'type', 'options', 'is_system', 'storage', 'column', 'position', 'created_by',
    ];

    protected function casts(): array
    {
        return ['options' => 'array', 'is_system' => 'boolean', 'position' => 'integer'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function isChoice(): bool
    {
        return in_array($this->type, ['category', 'multiple_choice'], true);
    }

    /** @return list<string> */
    public function optionValues(): array
    {
        return array_values(array_map(fn ($o) => (string) $o['value'], $this->options ?? []));
    }

    protected static function newFactory(): ContactAttributeFactory
    {
        return ContactAttributeFactory::new();
    }
}
