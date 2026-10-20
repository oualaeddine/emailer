<?php

namespace App\Modules\Forms\Http\Controllers;

use App\Domain\Enums\PermissionName;
use App\Domain\Enums\SettingValueType;
use App\Http\Controllers\Controller;
use App\Modules\Forms\Http\Requests\CaptchaSettingsRequest;
use App\Modules\Settings\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET / PUT /api/v1/forms/captcha-settings — the captcha used by public forms
 * (`forms.captcha_provider|site_key|secret`). `settings.manage` only; the secret is stored
 * encrypted (`is_secret`) and never returned: the response only says whether one is saved.
 */
class CaptchaSettingsController extends Controller
{
    public function __construct(private readonly SettingsService $settings) {}

    public function show(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasPermission(PermissionName::SettingsManage->value), 403);

        return $this->payload();
    }

    public function update(CaptchaSettingsRequest $request): JsonResponse
    {
        $user = $request->user();
        $provider = (string) $request->validated('provider');
        $this->settings->set('forms.captcha_provider', $provider, SettingValueType::String, false, $user);

        if ($provider !== 'none') {
            $this->settings->set('forms.captcha_site_key', trim((string) $request->validated('site_key')), SettingValueType::String, false, $user);
            $secret = trim((string) $request->validated('secret', ''));
            if ($secret !== '') {
                $this->settings->set('forms.captcha_secret', $secret, SettingValueType::String, true, $user);
            }
        }

        return $this->payload();
    }

    private function payload(): JsonResponse
    {
        $provider = (string) $this->settings->get('forms.captcha_provider', 'none');

        return response()->json(['data' => [
            'provider' => in_array($provider, CaptchaSettingsRequest::PROVIDERS, true) ? $provider : 'none',
            'site_key' => (string) $this->settings->get('forms.captcha_site_key', ''),
            'has_secret' => (string) $this->settings->get('forms.captcha_secret', '') !== '',
        ]]);
    }
}
