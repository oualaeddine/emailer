<?php

namespace App\Modules\DeliveryEngine\Rendering;

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Composer\Models\Draft;
use App\Modules\DeliveryEngine\Rendering\Contracts\RenderingStage;
use App\Modules\DeliveryEngine\Rendering\Stages\ConditionalBlocksStage;
use App\Modules\DeliveryEngine\Rendering\Stages\CssInlineStage;
use App\Modules\DeliveryEngine\Rendering\Stages\InlineImagesStage;
use App\Modules\DeliveryEngine\Rendering\Stages\LinkIdStage;
use App\Modules\DeliveryEngine\Rendering\Stages\MergeTagsStage;
use App\Modules\DeliveryEngine\Rendering\Stages\PlainTextStage;
use App\Modules\DeliveryEngine\Rendering\Stages\SignatureStage;
use App\Modules\DeliveryEngine\Rendering\Stages\ToFieldStage;
use App\Modules\DeliveryEngine\Rendering\Stages\TrackingStage;
use App\Modules\DeliveryEngine\Rendering\Stages\UtmRewriteStage;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Tracking\Models\Message;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Str;

/**
 * docs/38-rendering-pipeline.md — the single ordered pipeline between what
 * the user authored and what is sent (docs/45 §45.6.3, WP-63).
 *
 * Two phases over one ordered stage list:
 *  - `personalise` (link ids, stages 2–5b) runs once when the `Message` row
 *    is created ({@see createMessage()}); its output is the stored
 *    `messages.html_body` — the personalised web version the mirror page
 *    (`/m/{message}`) serves — and `messages.to_name` (the To display name,
 *    resolved with the merge contact of a test send).
 *  - `delivery` (5c–8) runs inside `SendEmailJob` on that stored HTML
 *    ({@see render()}); never persisted, so retries re-render the same bytes
 *    and nothing is ever double-wrapped.
 * Every stage is additionally idempotent on its own output.
 */
class RenderingPipeline
{
    /**
     * Exact order of docs/38 §38.2 (stage numbers in comments).
     *
     * @var list<class-string<RenderingStage>>
     */
    public const STAGES = [
        LinkIdStage::class,            // 1b data-link-id on the authored hrefs (before merge tags: one id per authored link)
        MergeTagsStage::class,         // 2  merge variables (+ links/campaign/sender tags)
        ToFieldStage::class,           // 2b To display name (header only, docs/45 §45.6.3)
        ConditionalBlocksStage::class, // 3  no-op in v1 (§38.5)
        SignatureStage::class,         // 4  signature
        UtmRewriteStage::class,        // 5a UTM parameters
        LinkIdStage::class,            // 5b data-link-id of links added since 1b (signature), before the click rewrite
        TrackingStage::class,          // 5c pixel + click rewrite (TrackingRenderer)
        CssInlineStage::class,         // 6  CSS inlining
        PlainTextStage::class,         // 7  plain-text alternative
        InlineImagesStage::class,      // 8  MIME assembly: cid: related parts
    ];

    /** docs/38 §38.6 — previews run stages 1, 2 and 6 only. */
    public const PREVIEW_STAGES = [MergeTagsStage::class, CssInlineStage::class];

    public function __construct(private readonly Container $container) {}

    /**
     * @return list<RenderingStage>
     */
    public function stages(?string $phase = null): array
    {
        $stages = array_map(fn (string $class): RenderingStage => $this->container->make($class), self::STAGES);

        return array_values(array_filter($stages, fn (RenderingStage $s): bool => $phase === null || $s->phase() === $phase));
    }

    /**
     * Runs the stages (all, or one phase) in order.
     */
    public function run(RenderedEmail $email, RenderContext $context, ?string $phase = null): RenderedEmail
    {
        foreach ($this->stages($phase) as $stage) {
            $email = $stage->process($email, $context);
        }

        return $email;
    }

    /**
     * Full pipeline (all stages) — same result as {@see createMessage()} followed by {@see render()}.
     */
    public function renderFull(string $subject, string $html, RenderContext $context): RenderedEmail
    {
        return $this->run(new RenderedEmail($subject, $html), $context);
    }

    /**
     * docs/38 §38.6 preview: merge tags (sample or real recipient) + CSS inlining, never tracking.
     */
    public function preview(string $subject, string $html, Recipient $recipient, ?Campaign $campaign = null): RenderedEmail
    {
        $context = new RenderContext($recipient, null, $campaign, null, null, true);
        $email = new RenderedEmail($subject, $html);

        foreach (self::PREVIEW_STAGES as $class) {
            $email = $this->container->make($class)->process($email, $context);
        }

        return $email;
    }

    /**
     * Creates (and saves) a queued `Message` whose subject / html_body are
     * the personalised output (phase `personalise`). The uuid is assigned
     * up-front so `{{ mirror }}` can point at the message being created.
     *
     * @param  array<string, mixed>  $attributes  message columns (recipient_id, status, queued_at, is_test, …)
     */
    public function createMessage(
        array $attributes,
        string $subject,
        string $html,
        Recipient $recipient,
        ?Campaign $campaign = null,
        ?Recipient $mergeWith = null,
        ?Draft $draft = null,
    ): Message {
        $message = new Message;
        $message->forceFill($attributes);
        $message->uuid = $message->uuid ?: (string) Str::uuid();

        $context = new RenderContext($recipient, $message, $campaign, $draft, $mergeWith);
        $personalised = $this->run(new RenderedEmail($subject, $html), $context, RenderingStage::PHASE_PERSONALISE);

        $message->forceFill([
            'subject' => $personalised->subject,
            'html_body' => $personalised->html,
            'to_name' => $personalised->toName,
        ])->save();

        return $message;
    }

    /**
     * Send-time rendering (phase `delivery`) of a stored message, called by SendEmailJob.
     * The To display name is the one stored at creation (`messages.to_name`).
     */
    public function render(Message $message): RenderedEmail
    {
        $context = $this->contextFor($message);
        $email = new RenderedEmail((string) $message->subject, (string) $message->html_body, null, $message->getAttribute('to_name'));

        // Messages stored before `to_name` existed: resolve it now (delivery recipient's data).
        if ($email->toName === null && trim((string) ($context->campaign?->to_field ?? '')) !== '') {
            $email = $this->container->make(ToFieldStage::class)->process($email, $context);
        }

        return $this->run($email, $context, RenderingStage::PHASE_DELIVERY);
    }

    /**
     * The campaign HTML as authored, with only the UTM rewrite and the
     * `data-link-id` attributes applied (no merge tags, no tracking): the
     * ids match the `link_id` of the campaign's click counters, so the
     * report click map can place them (docs/43 §43.6.3).
     */
    public function clickMapHtml(Campaign $campaign): string
    {
        $message = new Message;
        $message->uuid = (string) Str::uuid();
        $context = new RenderContext(new Recipient(['custom_fields' => []]), $message, $campaign);

        $email = new RenderedEmail((string) $campaign->subject, (string) $campaign->html_body);
        foreach ([UtmRewriteStage::class, LinkIdStage::class] as $class) {
            $email = $this->container->make($class)->process($email, $context);
        }

        return $email->html;
    }

    public function contextFor(Message $message): RenderContext
    {
        $campaign = $message->campaign_id !== null ? Campaign::query()->find($message->campaign_id) : null;

        return new RenderContext($message->recipient, $message, $campaign, $message->draft);
    }
}
