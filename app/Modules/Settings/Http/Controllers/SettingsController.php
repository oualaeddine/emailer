<?php

namespace App\Modules\Settings\Http\Controllers;

use App\Domain\Enums\PermissionName;
use App\Domain\Enums\SettingValueType;
use App\Http\Controllers\Controller;
use App\Modules\Settings\Http\Requests\UpdateSettingsRequest;
use App\Modules\Settings\Http\Resources\SettingResource;
use App\Modules\Settings\Services\SettingsService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * docs/29-api-specification.md §29.11 — GET/PATCH /api/v1/settings.
 */
class SettingsController extends Controller
{
    public function __construct(private readonly SettingsService $settings)
    {
    }

    public function index(): AnonymousResourceCollection
    {
        Gate::authorize(PermissionName::SettingsBrandingOnly->value);

        return SettingResource::collection($this->settings->all());
    }

    public function update(UpdateSettingsRequest $request): AnonymousResourceCollection
    {
        foreach ($request->validated('settings') as $key => $value) {
            $this->settings->set($key, $value, SettingValueType::String, false, $request->user());
        }

        return SettingResource::collection($this->settings->all());
    }
}
