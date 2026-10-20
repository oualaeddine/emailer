<?php

namespace App\Modules\DeliveryEngine\Rendering\Stages;

use App\Modules\Composer\Services\MergeVariableResolver;
use App\Modules\DeliveryEngine\Rendering\Contracts\RenderingStage;
use App\Modules\DeliveryEngine\Rendering\RenderContext;
use App\Modules\DeliveryEngine\Rendering\RenderedEmail;

/**
 * docs/45 §45.6.3 — campaign `to_field` (e.g. `{{ contact.PRENOM }} {{ contact.NOM }}`,
 * Brevo-style `{FNAME} {LNAME}` accepted too) rendered into the To header
 * display name. Header only: never touches the HTML.
 */
class ToFieldStage implements RenderingStage
{
    private const BREVO_ALIASES = ['FNAME' => 'PRENOM', 'FIRSTNAME' => 'PRENOM', 'LNAME' => 'NOM', 'LASTNAME' => 'NOM'];

    public function __construct(private readonly MergeVariableResolver $resolver) {}

    public function phase(): string
    {
        return self::PHASE_DELIVERY;
    }

    public function process(RenderedEmail $email, RenderContext $context): RenderedEmail
    {
        $template = trim((string) ($context->campaign?->to_field ?? ''));

        if ($template === '') {
            return $email->withToName(null);
        }

        $template = (string) preg_replace_callback(
            '/(?<!\{)\{\s*([A-Za-z][A-Za-z0-9_]*)\s*\}(?!\})/',
            fn (array $m): string => '{{ contact.'.(self::BREVO_ALIASES[strtoupper($m[1])] ?? strtoupper($m[1])).' }}',
            $template,
        );

        $name = $this->resolver->resolve($template, $context->mergeRecipient);
        $name = trim((string) preg_replace('/\s+/u', ' ', str_replace(["\r", "\n"], ' ', $name)));

        return $email->withToName($name === '' ? null : $name);
    }
}
