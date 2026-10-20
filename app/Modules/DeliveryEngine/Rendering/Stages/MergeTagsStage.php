<?php

namespace App\Modules\DeliveryEngine\Rendering\Stages;

use App\Modules\Composer\Services\MergeVariableResolver;
use App\Modules\DeliveryEngine\Rendering\Contracts\RenderingStage;
use App\Modules\DeliveryEngine\Rendering\RenderContext;
use App\Modules\DeliveryEngine\Rendering\RenderedEmail;
use App\Modules\DeliveryEngine\Rendering\Support\SystemUrls;
use App\Modules\Senders\Models\Sender;

/**
 * docs/38 §38.2 stage 2 — merge variables (docs/43 §43.6.6, docs/45 §45.5.1):
 * `{{ contact.NAME | default:"…" }}`, legacy `{{recipient.*}}`, `{{ mirror }}`,
 * `{{ unsubscribe }}`, `{{ update_profile }}`, `{{ campaign.* }}`, `{{ sender.* }}`.
 * Subject resolved as plain text, HTML body with escaped values.
 */
class MergeTagsStage implements RenderingStage
{
    public function __construct(
        private readonly MergeVariableResolver $resolver,
        private readonly SystemUrls $urls,
    ) {}

    public function phase(): string
    {
        return self::PHASE_PERSONALISE;
    }

    public function process(RenderedEmail $email, RenderContext $context): RenderedEmail
    {
        $unsubscribe = $this->urls->unsubscribe($context);
        $variables = $this->variables($context);

        return $email
            ->withSubject($this->resolver->resolve($email->subject, $context->mergeRecipient, $unsubscribe, $variables))
            ->withHtml($this->resolver->resolveHtml($email->html, $context->mergeRecipient, $unsubscribe, $variables));
    }

    /**
     * @return array<string, string|null>
     */
    private function variables(RenderContext $context): array
    {
        $campaign = $context->campaign;
        $sender = $context->remember('sender', fn (): ?Sender => $campaign?->sender_id !== null
            ? Sender::query()->find($campaign->sender_id)
            : null);

        return [
            'mirror' => $this->urls->mirror($context),
            'update_profile' => $this->urls->updateProfile($context),
            'campaign.name' => $campaign?->name,
            'campaign.subject' => $campaign?->subject,
            'sender.name' => $sender?->name,
            'sender.email' => $sender?->email,
        ];
    }
}
