<?php

namespace App\Modules\Suppression\Preferences\Services;

use App\Modules\Recipients\Models\Recipient;
use App\Modules\Settings\Services\SettingsService;
use App\Modules\Suppression\Preferences\Mail\DoubleOptInMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/** docs/45 §45.6.6 — double opt-in confirmation mail + confirmation. */
class DoubleOptInService
{
    public const SOURCE = 'double_opt_in';

    public function __construct(
        private readonly PreferenceTokenService $tokens,
        private readonly SettingsService $settings,
    ) {}

    /** Mails the confirmation link; returns it. */
    public function send(Recipient $recipient): string
    {
        $url = $this->tokens->confirmUrl($recipient->email);
        $html = null;
        $subject = 'Confirmez votre inscription';

        $templateUuid = $this->settings->get('forms.default_doi_template_id');
        if (is_string($templateUuid) && $templateUuid !== '') {
            $template = DB::table('templates')->where('uuid', $templateUuid)->whereNull('deleted_at')->first(['html_content', 'subject']);
            if ($template !== null && str_contains((string) $template->html_content, '{{ doi_link }}')) {
                $html = str_replace('{{ doi_link }}', e($url), (string) $template->html_content);
                $subject = $template->subject ?: $subject;
            }
        }

        Mail::to($recipient->email)->send(new DoubleOptInMail($url, $html, $subject));

        return $url;
    }

    /** Idempotent: the first confirmation timestamp is kept. */
    public function confirm(Recipient $recipient): Recipient
    {
        if ($recipient->getAttribute('opt_in_at') === null) {
            DB::table('recipients')->where('id', $recipient->id)->update([
                'opt_in_at' => now(),
                'opt_in_source' => self::SOURCE,
            ]);
            $recipient->refresh();
        }

        return $recipient;
    }
}
