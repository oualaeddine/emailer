<?php

namespace App\Modules\DeliveryEngine\Rendering\Stages;

use App\Modules\DeliveryEngine\Rendering\Contracts\RenderingStage;
use App\Modules\DeliveryEngine\Rendering\RenderContext;
use App\Modules\DeliveryEngine\Rendering\RenderedEmail;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Recipients\Segments\Contracts\RuleTreeCompiler;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * docs/38 §38.2 stage 3 / §38.5 — conditional (dynamic-content) blocks,
 * evaluated per recipient at personalise time (WP-61, docs/45 §45.7.1).
 *
 * The EmailBuilder wraps each dynamic-content section in
 * `<mj-raw><div data-dynamic-rule="{id}"></mj-raw> … <mj-raw></div></mj-raw>`,
 * so the compiled HTML carries `<div data-dynamic-rule="{id}"> … </div>`
 * and the rule of `{id}` lives in `design_json.dynamic_blocks[]`
 * (`{ id, type: "dynamic_content", variants: [{ condition: RuleTree, … }], … }`).
 *
 * For each marker: the block is kept (marker removed) when the merge
 * recipient matches the rule ({@see RuleTreeCompiler::matches()}), removed
 * entirely otherwise. A missing / malformed rule, or a recipient that is
 * not stored (nothing to evaluate against), keeps the block; a missing or
 * malformed rule is logged as a warning. Idempotent: the output carries no
 * marker.
 */
class ConditionalBlocksStage implements RenderingStage
{
    public const ATTRIBUTE = 'data-dynamic-rule';

    private const OPEN_TAG = '#<div\s+data-dynamic-rule\s*=\s*"([^"]*)"[^>]*>#i';

    private const TOKENS = '#<!--.*?-->|<div\b[^>]*>|</div\s*>#is';

    public function __construct(private readonly RuleTreeCompiler $rules) {}

    public function phase(): string
    {
        return self::PHASE_PERSONALISE;
    }

    public function process(RenderedEmail $email, RenderContext $context): RenderedEmail
    {
        if (stripos($email->html, self::ATTRIBUTE) === false) {
            return $email;
        }

        $blocks = $this->dynamicBlocks($context->campaign?->design_json);
        $recipient = $context->mergeRecipient;
        $html = $email->html;
        $offset = 0;

        while (preg_match(self::OPEN_TAG, $html, $open, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $start = $open[0][1];
            $innerStart = $start + strlen($open[0][0]);
            $id = $open[1][0];
            $close = $this->matchingClose($html, $innerStart);

            if ($close === null) {
                // Unbalanced markup: drop the marker only, keep the content.
                Log::warning('Dynamic content block without a closing tag: kept.', $this->logContext($id, $context));
                $html = substr($html, 0, $start).substr($html, $innerStart);
                $offset = $start;

                continue;
            }

            [$closeStart, $closeEnd] = $close;

            if ($this->shouldShow($id, $blocks, $recipient, $context)) {
                $html = substr($html, 0, $start)
                    .substr($html, $innerStart, $closeStart - $innerStart)
                    .substr($html, $closeEnd);
            } else {
                $html = substr($html, 0, $start).substr($html, $closeEnd);
            }

            $offset = $start;
        }

        return $html === $email->html ? $email : $email->withHtml($html);
    }

    /**
     * `design_json.dynamic_blocks` → [id => rule tree | null (malformed)].
     *
     * @return array<string, array<string, mixed>|null>
     */
    private function dynamicBlocks(mixed $design): array
    {
        if (! is_array($design) || ! is_array($design['dynamic_blocks'] ?? null)) {
            return [];
        }

        $blocks = [];
        foreach ($design['dynamic_blocks'] as $block) {
            if (! is_array($block) || ! is_string($block['id'] ?? null) || $block['id'] === '') {
                continue;
            }
            $blocks[$block['id']] = $this->conditionOf($block);
        }

        return $blocks;
    }

    /**
     * The block's rule: `variants[0].condition` (frozen `DynamicContentBlock`
     * shape), or a bare `condition` key.
     *
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>|null
     */
    private function conditionOf(array $block): ?array
    {
        $condition = $block['variants'][0]['condition'] ?? ($block['condition'] ?? null);

        return is_array($condition) ? $condition : null;
    }

    /**
     * @param  array<string, array<string, mixed>|null>  $blocks
     */
    private function shouldShow(string $id, array $blocks, Recipient $recipient, RenderContext $context): bool
    {
        if (! array_key_exists($id, $blocks)) {
            Log::warning('Dynamic content block without a rule: kept.', $this->logContext($id, $context));

            return true;
        }

        $tree = $blocks[$id];
        if ($tree === null) {
            Log::warning('Dynamic content block with a malformed rule: kept.', $this->logContext($id, $context));

            return true;
        }

        if ($recipient->getKey() === null) {
            return true;
        }

        try {
            return $context->remember('dynamic-rule:'.$id, fn (): bool => $this->rules->matches($recipient, $tree));
        } catch (Throwable $e) {
            Log::warning('Dynamic content block with a malformed rule: kept.', $this->logContext($id, $context) + ['error' => $e->getMessage()]);

            return true;
        }
    }

    /**
     * Offsets of the `</div>` closing the marker div opened before
     * `$from`, skipping comments (Outlook conditionals) and nested divs.
     *
     * @return array{0: int, 1: int}|null
     */
    private function matchingClose(string $html, int $from): ?array
    {
        $depth = 1;
        $offset = $from;

        while (preg_match(self::TOKENS, $html, $token, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $text = $token[0][0];
            $position = $token[0][1];
            $offset = $position + strlen($text);

            if (str_starts_with($text, '<!--')) {
                continue;
            }
            if (str_starts_with($text, '</')) {
                $depth--;
                if ($depth === 0) {
                    return [$position, $offset];
                }

                continue;
            }
            $depth++;
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function logContext(string $id, RenderContext $context): array
    {
        return [
            'dynamic_rule' => $id,
            'campaign_id' => $context->campaign?->getKey(),
            'message' => $context->message?->uuid,
            'recipient_id' => $context->mergeRecipient->getKey(),
        ];
    }
}
