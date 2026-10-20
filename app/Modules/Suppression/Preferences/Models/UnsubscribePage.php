<?php

namespace App\Modules\Suppression\Preferences\Models;

use App\Support\Concerns\HasUuid;
use Database\Factories\UnsubscribePageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * docs/45 §45.8 Slot M — `unsubscribe_pages`.
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property bool $is_default
 * @property array<string, mixed> $content
 * @property array<string, mixed> $design
 */
class UnsubscribePage extends Model
{
    /** @use HasFactory<UnsubscribePageFactory> */
    use HasFactory, HasUuid;

    protected $fillable = ['name', 'is_default', 'content', 'design', 'created_by'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_default' => 'boolean', 'content' => 'array', 'design' => 'array'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected static function newFactory(): UnsubscribePageFactory
    {
        return UnsubscribePageFactory::new();
    }
}
