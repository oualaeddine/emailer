<?php

namespace App\Modules\Suppression\Preferences\Services;

/** docs/45 §45.6.6 — default content / design of the two page records. */
final class PageDefaults
{
    /** @return array<string, mixed> */
    public static function unsubscribeContent(): array
    {
        return [
            'title' => 'Se désinscrire',
            'body' => 'Confirmez-vous ne plus vouloir recevoir nos e-mails ?',
            'button_label' => 'Me désinscrire',
            'confirmation_title' => 'Vous êtes désinscrit',
            'confirmation_message' => 'Vous ne recevrez plus nos e-mails.',
            'show_reason_survey' => true,
            'reasons' => [
                'Je reçois trop d’e-mails',
                'Le contenu ne m’intéresse pas',
                'Je ne me suis jamais inscrit',
                'Autre',
            ],
            'show_preferences_link' => true,
            'resubscribe_enabled' => false,
        ];
    }

    /** @return array<string, mixed> */
    public static function profileContent(): array
    {
        return [
            'title' => 'Mettre à jour mes préférences',
            'intro' => 'Choisissez les communications que vous souhaitez recevoir.',
            'submit_label' => 'Enregistrer',
            'success_message' => 'Vos préférences ont été enregistrées.',
        ];
    }

    /** @return array<string, mixed> */
    public static function design(): array
    {
        return [
            'logo_url' => null,
            'primary_color' => '#0F6CBD',
            'background_color' => '#F5F5F5',
            'text_color' => '#242424',
            'font_family' => 'Segoe UI, sans-serif',
            'button_text_color' => '#FFFFFF',
            'border_radius' => 4,
        ];
    }
}
