<?php

namespace App\Modules\Settings\Services;

use App\Domain\Enums\SettingValueType;
use App\Domain\Events\SettingsUpdated;
use App\Modules\Identity\Models\User;
use App\Modules\Settings\Models\Setting;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * docs/29-api-specification.md §29.11 — GET/PATCH /api/v1/settings.
 * docs/02-system-architecture.md §2.7 — Settings cached, invalidated on write.
 */
class SettingsService
{
    public const CACHE_PREFIX = 'settings.';

    public function get(string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever(self::CACHE_PREFIX.$key, function () use ($key, $default) {
            $setting = Setting::query()->where('key', $key)->first();

            return $setting?->value() ?? $default;
        });
    }

    /**
     * @return Collection<int, Setting>
     */
    public function all(): Collection
    {
        return Setting::query()->orderBy('key')->get();
    }

    public function set(string $key, mixed $value, SettingValueType $type, bool $isSecret, User $updatedBy): Setting
    {
        $setting = Setting::query()->firstOrNew(['key' => $key]);
        $oldValue = $setting->exists ? $setting->value() : null;

        $setting->value_type = $type->value;
        $setting->is_secret = $isSecret;
        $setting->setValue($value);
        $setting->updated_by = $updatedBy->id;
        $setting->updated_at = now();
        $setting->save();

        // Cache invalidation happens in ConfigCacheInvalidationListener,
        // synchronously, on this event — not inline here — so the event
        // catalog (docs/31-events.md) stays the single source of truth for
        // every side effect of a settings write.
        SettingsUpdated::dispatch(
            $key,
            $isSecret ? '[REDACTED]' : $oldValue,
            $isSecret ? '[REDACTED]' : $value,
            $isSecret,
            $updatedBy,
        );

        return $setting;
    }

    /**
     * Writes a setting on behalf of the system itself (e.g. a scheduled
     * sync job's internal progress cursor) rather than a human actor —
     * skips the `SettingsUpdated` event entirely, since there is no user
     * action to audit or notify about and no `updated_by` to record.
     * Never used for anything a user can see/edit through the Settings UI.
     */
    public function setSystemValue(string $key, mixed $value, SettingValueType $type): Setting
    {
        $setting = Setting::query()->firstOrNew(['key' => $key]);
        $setting->value_type = $type->value;
        $setting->is_secret = false;
        $setting->setValue($value);
        $setting->updated_at = now();
        $setting->save();

        Cache::forget(self::CACHE_PREFIX.$key);

        return $setting;
    }
}
