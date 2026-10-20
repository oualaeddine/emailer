<?php

namespace App\Modules\DeliveryEngine\Rendering\Http;

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\DeliveryEngine\Rendering\RenderingPipeline;
use App\Modules\DeliveryEngine\Rendering\Support\MirrorHtml;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Tracking\Models\Message;
use Illuminate\Http\Response;

/**
 * docs/45 §45.6.3 — web version of an e-mail ("voir dans le navigateur").
 * Public, gated by the `signed` middleware (routes/web/mirror.php).
 *
 *  - `GET /m/{message}`: the personalised stored HTML of one message, with
 *    tracking stripped; counts a mirror click on the campaign (not for test
 *    messages).
 *  - `GET /m/c/{campaign}`: anonymous public version of a campaign (share
 *    link), merge tags rendered with their defaults.
 *
 * The e-mail is shown inside a sandboxed `srcdoc` iframe (no scripts, no
 * same-origin access), so its markup can never act on this page.
 */
class MirrorController
{
    public function show(Message $message): Response
    {
        abort_if(blank($message->html_body), 404);

        if ($message->campaign_id !== null && ! (bool) ($message->getAttribute('is_test') ?? false)) {
            Campaign::query()->whereKey($message->campaign_id)->increment('mirror_clicks');
        }

        return $this->page((string) $message->subject, MirrorHtml::clean((string) $message->html_body));
    }

    public function campaign(Campaign $campaign, RenderingPipeline $pipeline): Response
    {
        abort_if(blank($campaign->html_body), 404);

        $anonymous = new Recipient(['custom_fields' => []]);
        $rendered = $pipeline->preview((string) $campaign->subject, (string) $campaign->html_body, $anonymous, $campaign);

        return $this->page($rendered->subject, MirrorHtml::clean($rendered->html));
    }

    private function page(string $subject, string $html): Response
    {
        return response()
            ->view('mirror.show', [
                'subject' => $subject,
                'html' => '<base target="_blank">'.$html,
            ])
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Referrer-Policy', 'no-referrer');
    }
}
