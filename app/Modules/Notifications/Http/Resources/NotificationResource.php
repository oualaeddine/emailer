<?php

namespace App\Modules\Notifications\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * docs/41-notification-center.md §41.5 — bell panel list item shape.
 *
 * `type` is reduced from the notification class's FQCN (e.g.
 * `App\Modules\Identity\Notifications\RoleChangedNotification`, per
 * {@see \App\Modules\Identity\Notifications\RoleChangedNotification}) down
 * to a short, readable snake_case slug (`role_changed`) — the frontend
 * never needs to know the backend's namespace structure, and every
 * existing/future `ShouldQueue` `Notification` class dispatched via the
 * `database` channel (docs/31-events.md listener map) is covered by the
 * same generic reduction, not a class-by-class mapping table.
 *
 * @mixin \Illuminate\Notifications\DatabaseNotification
 */
class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => self::shortType($this->type),
            'data' => $this->data,
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    private static function shortType(string $fqcn): string
    {
        $basename = class_basename($fqcn);

        if (Str::endsWith($basename, 'Notification')) {
            $basename = substr($basename, 0, -strlen('Notification'));
        }

        return Str::snake($basename);
    }
}
