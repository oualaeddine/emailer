<?php

namespace App\Domain\Enums;

/**
 * docs/07-ui-design.md §7.11 — Theming & Branding (Settings-driven).
 * docs/26-rbac.md §26.3 — the exact subset of `settings` keys a
 * `settings.branding_only` grant (Marketing Manager) may write, as opposed
 * to `settings.manage` (Administrator) which may write any key.
 */
enum BrandingSettingKey: string
{
    case OrganizationName = 'branding.organization_name';
    case LogoPath = 'branding.logo_path';
    case BrandColor = 'branding.brand_color';
    case DefaultTheme = 'branding.default_theme';

    public function type(): SettingValueType
    {
        return SettingValueType::String;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $key) => $key->value, self::cases());
    }
}
