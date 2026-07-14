<?php

namespace Database\Seeders;

use App\Domain\Enums\BrandingSettingKey;
use App\Domain\Enums\SettingValueType;
use App\Modules\Settings\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * docs/07-ui-design.md §7.11 — default branding values for a fresh install.
 */
class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            BrandingSettingKey::OrganizationName->value => 'PageJaunes Mailer',
            BrandingSettingKey::LogoPath->value => null,
            BrandingSettingKey::BrandColor->value => '#7740D6',
            BrandingSettingKey::DefaultTheme->value => 'light',
        ];

        foreach ($defaults as $key => $value) {
            $setting = Setting::query()->firstOrNew(['key' => $key]);

            if ($setting->exists) {
                continue;
            }

            $setting->value_type = SettingValueType::String->value;
            $setting->is_secret = false;
            $setting->setValue($value);
            $setting->updated_at = now();
            $setting->save();
        }
    }
}
