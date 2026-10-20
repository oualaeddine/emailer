<?php

namespace App\Modules\Suppression\Preferences\Services;

use App\Modules\Recipients\Models\Recipient;
use App\Modules\Settings\Services\SettingsService;
use App\Modules\Suppression\Preferences\Models\UnsubscribePage;
use App\Modules\Suppression\Preferences\Models\UpdateProfileForm;
use Illuminate\Support\Facades\Storage;

/** Builds the data of `resources/views/preferences/page.blade.php` (public page + admin preview). */
class PageViewBuilder
{
    private const ATTRIBUTE_COLUMNS = [
        'PRENOM' => 'first_name', 'FIRSTNAME' => 'first_name', 'NOM' => 'last_name',
        'LASTNAME' => 'last_name', 'SOCIETE' => 'company_name',
    ];

    public function __construct(private readonly SettingsService $settings, private readonly PreferenceService $preferences) {}

    /**
     * Resolved design: record values, then branding settings, then defaults.
     *
     * @param  array<string, mixed>  $design
     * @return array<string, mixed>
     */
    public function design(array $design): array
    {
        $defaults = PageDefaults::design();
        $brand = $this->settings->get('branding.brand_color');
        $logo = $design['logo_url'] ?? null;
        if (! $logo) {
            $path = $this->settings->get('branding.logo_path');
            $logo = $path ? (str_starts_with((string) $path, 'http') ? $path : Storage::disk('public')->url((string) $path)) : null;
        }

        $color = fn (mixed $v, string $fallback): string => is_string($v) && preg_match('/^#[0-9A-Fa-f]{6}$/', $v) ? $v : $fallback;
        $primary = $design['primary_color'] ?? (is_string($brand) ? $brand : null);

        return [
            'logo' => $logo,
            'organization' => (string) $this->settings->get('branding.organization_name', ''),
            'primary' => $color($primary, $defaults['primary_color']),
            'background' => $color($design['background_color'] ?? null, $defaults['background_color']),
            'text' => $color($design['text_color'] ?? null, $defaults['text_color']),
            'font' => preg_replace('/[^A-Za-z0-9 ,_-]/', '', (string) ($design['font_family'] ?? $defaults['font_family'])) ?: $defaults['font_family'],
            'button_text' => $color($design['button_text_color'] ?? null, $defaults['button_text_color']),
            'radius' => (int) ($design['border_radius'] ?? $defaults['border_radius']),
        ];
    }

    /** @return array<string, mixed> */
    public function preferencesPage(Recipient $recipient, ?UpdateProfileForm $form, string $action, ?string $message = null): array
    {
        $content = array_merge(PageDefaults::profileContent(), $form?->content ?? []);
        $values = [];
        foreach ($form?->fields ?? [] as $field) {
            $name = strtoupper($field['attribute']);
            $column = self::ATTRIBUTE_COLUMNS[$name] ?? null;
            $values[$field['attribute']] = (string) (($recipient->custom_fields[$field['attribute']] ?? null)
                ?? ($recipient->custom_fields[$name] ?? null)
                ?? ($column ? $recipient->getAttribute($column) : null) ?? '');
        }

        return [
            'mode' => 'preferences',
            'design' => $this->design($form?->design ?? []),
            'content' => $content,
            'fields' => $form?->fields ?? [],
            'values' => $values,
            'lists' => $this->preferences->listsFor($recipient, $form),
            'email' => $recipient->email,
            'action' => $action,
            'message' => $message,
        ];
    }

    /** @return array<string, mixed> */
    public function unsubscribePage(?UnsubscribePage $page, ?string $message = null): array
    {
        return [
            'mode' => 'unsubscribe',
            'design' => $this->design($page?->design ?? []),
            'content' => array_merge(PageDefaults::unsubscribeContent(), $page?->content ?? []),
            'message' => $message,
        ];
    }

    /** @return array<string, mixed> */
    public function simple(string $mode, string $title, string $message, ?UpdateProfileForm $form = null): array
    {
        return [
            'mode' => $mode,
            'design' => $this->design($form?->design ?? $this->preferences->defaultForm()?->design ?? []),
            'content' => ['title' => $title],
            'message' => $message,
        ];
    }
}
