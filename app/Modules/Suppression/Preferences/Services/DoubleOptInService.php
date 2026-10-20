<?php

namespace App\Modules\Suppression\Preferences\Services;

use App\Modules\Composer\Services\MergeVariableResolver;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Settings\Services\SettingsService;
use App\Modules\Suppression\Preferences\Events\DoubleOptInConfirmed;
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
        private readonly MergeVariableResolver $resolver,
    ) {}

    /** Confirmation-link merge tags a template may carry (`{{ doubleoptin }}` is the documented one). */
    public const LINK_TAGS = ['doubleoptin', 'doi_link'];

    /**
     * Queues the confirmation mail; returns the link. `$templateId` (templates.id, e.g. a form's
     * `doi_template_id`) selects the e-mail when it is usable (existing, not archived, not deleted,
     * with HTML); otherwise the workspace default template, otherwise the built-in view.
     */
    public function send(Recipient $recipient, ?int $templateId = null): string
    {
        $url = $this->tokens->confirmUrl($recipient->email);
        $html = null;
        $subject = 'Confirmez votre inscription';

        $custom = $templateId !== null ? $this->usableTemplate(['id' => $templateId]) : null;
        if ($custom !== null) {
            $html = $this->renderTemplate((string) $custom->html_content, $recipient, $url);
            $subject = $custom->subject ?: $subject;
        } else {
            $templateUuid = $this->settings->get('forms.default_doi_template_id');
            if (is_string($templateUuid) && $templateUuid !== '') {
                $template = $this->usableTemplate(['uuid' => $templateUuid]);
                if ($template !== null && $this->hasLinkTag((string) $template->html_content)) {
                    $html = $this->renderTemplate((string) $template->html_content, $recipient, $url);
                    $subject = $template->subject ?: $subject;
                }
            }
        }

        Mail::to($recipient->email)->queue(new DoubleOptInMail($url, $html, $subject));

        return $url;
    }

    /** @param  array<string, mixed>  $where */
    private function usableTemplate(array $where): ?object
    {
        $template = DB::table('templates')->where($where)->whereNull('deleted_at')->where('is_archived', false)
            ->first(['html_content', 'subject']);

        return $template !== null && trim((string) $template->html_content) !== '' ? $template : null;
    }

    private function hasLinkTag(string $html): bool
    {
        return preg_match('/\{\{\s*('.implode('|', self::LINK_TAGS).')\s*(?:\|[^}]*)?\}\}/i', $html) === 1;
    }

    /** Merge tags personalised, the link inserted; a template without the tag gets a labelled link appended. */
    private function renderTemplate(string $html, Recipient $recipient, string $url): string
    {
        $had = $this->hasLinkTag($html);
        $out = $this->resolver->resolveHtml($html, $recipient, null, array_fill_keys(self::LINK_TAGS, $url));
        if ($had) {
            return $out;
        }

        $link = '<p style="text-align:center;margin:24px 0;font-family:Segoe UI,Arial,sans-serif;">'
            .'<a href="'.e($url).'" style="display:inline-block;padding:12px 24px;background:#0f6cbd;color:#ffffff;text-decoration:none;border-radius:4px;">'
            .'Confirmer mon inscription</a></p>';

        return preg_match('/<\/body>/i', $out) === 1
            ? (string) preg_replace('/<\/body>/i', $link.'</body>', $out, 1)
            : $out.$link;
    }

    /**
     * Idempotent: the first confirmation timestamp is kept. Fires
     * DoubleOptInConfirmed on every call so other modules (Forms) can apply
     * what waited for the confirmation without this module depending on them.
     */
    public function confirm(Recipient $recipient): Recipient
    {
        $first = $recipient->getAttribute('opt_in_at') === null;
        if ($first) {
            DB::table('recipients')->where('id', $recipient->id)->update([
                'opt_in_at' => now(),
                'opt_in_source' => self::SOURCE,
            ]);
        }

        DoubleOptInConfirmed::dispatch((int) $recipient->id, $first);

        return $recipient->refresh();
    }
}
