<?php

namespace App\Modules\Suppression\Preferences\Models;

use App\Support\Concerns\HasUuid;
use Database\Factories\UpdateProfileFormFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * docs/45 §45.8 Slot M — `update_profile_forms`.
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property bool $is_default
 * @property list<array{attribute: string, label: string, required: bool}> $fields
 * @property list<string> $list_ids list uuids
 * @property array<string, mixed> $content
 * @property array<string, mixed> $design
 */
class UpdateProfileForm extends Model
{
    /** @use HasFactory<UpdateProfileFormFactory> */
    use HasFactory, HasUuid;

    protected $fillable = ['name', 'is_default', 'fields', 'list_ids', 'content', 'design', 'created_by'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_default' => 'boolean', 'fields' => 'array', 'list_ids' => 'array', 'content' => 'array', 'design' => 'array'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected static function newFactory(): UpdateProfileFormFactory
    {
        return UpdateProfileFormFactory::new();
    }
}
