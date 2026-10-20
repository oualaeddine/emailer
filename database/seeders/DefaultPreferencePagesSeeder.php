<?php

namespace Database\Seeders;

use App\Domain\Enums\SettingValueType;
use App\Modules\Settings\Models\Setting;
use App\Modules\Settings\Services\SettingsService;
use App\Modules\Suppression\Preferences\Models\UnsubscribePage;
use App\Modules\Suppression\Preferences\Models\UpdateProfileForm;
use App\Modules\Suppression\Preferences\Services\PageDefaults;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * docs/45 §45.6.6 / §45.8 Slot M — one default unsubscribe page, one default
 * update-profile form and the double opt-in transactional template (id kept in
 * setting `forms.default_doi_template_id`). Idempotent.
 */
class DefaultPreferencePagesSeeder extends Seeder
{
    public const DOI_TEMPLATE_NAME = 'Confirmation d\'inscription (double opt-in)';

    public const DOI_SETTING = 'forms.default_doi_template_id';

    public function run(): void
    {
        if (! UnsubscribePage::query()->exists()) {
            UnsubscribePage::query()->create([
                'name' => 'Page par défaut',
                'is_default' => true,
                'content' => PageDefaults::unsubscribeContent(),
                'design' => PageDefaults::design(),
            ]);
        } elseif (! UnsubscribePage::query()->where('is_default', true)->exists()) {
            UnsubscribePage::query()->orderBy('id')->first()?->update(['is_default' => true]);
        }

        if (! UpdateProfileForm::query()->exists()) {
            UpdateProfileForm::query()->create([
                'name' => 'Formulaire par défaut',
                'is_default' => true,
                'fields' => [
                    ['attribute' => 'PRENOM', 'label' => 'Prénom', 'required' => false],
                    ['attribute' => 'NOM', 'label' => 'Nom', 'required' => false],
                ],
                'list_ids' => [],
                'content' => PageDefaults::profileContent(),
                'design' => PageDefaults::design(),
            ]);
        } elseif (! UpdateProfileForm::query()->where('is_default', true)->exists()) {
            UpdateProfileForm::query()->orderBy('id')->first()?->update(['is_default' => true]);
        }

        $this->seedDoubleOptInTemplate();
    }

    private function seedDoubleOptInTemplate(): void
    {
        if (! Schema::hasTable('templates') || ! Schema::hasColumn('templates', 'scope')) {
            return;
        }

        $existing = Setting::query()->where('key', self::DOI_SETTING)->first();
        if ($existing !== null && $existing->value()) {
            return;
        }

        $row = DB::table('templates')->where('name', self::DOI_TEMPLATE_NAME)->where('scope', 'transactional')->first(['uuid']);
        $uuid = $row->uuid ?? null;
        if ($uuid === null) {
            $uuid = (string) Str::uuid();
            DB::table('templates')->insert([
                'uuid' => $uuid,
                'name' => self::DOI_TEMPLATE_NAME,
                'category' => 'transactional',
                'html_content' => '<p>Bonjour,</p><p>Merci de confirmer votre inscription en cliquant sur le lien ci-dessous.</p>'
                    .'<p><a href="{{ doi_link }}">Confirmer mon inscription</a></p>'
                    .'<p>Si vous n’êtes pas à l’origine de cette demande, ignorez simplement cet e-mail.</p>',
                'is_archived' => false,
                'editor_type' => 'html',
                'scope' => 'transactional',
                'subject' => 'Confirmez votre inscription',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        app(SettingsService::class)->setSystemValue(self::DOI_SETTING, $uuid, SettingValueType::String);
    }
}
