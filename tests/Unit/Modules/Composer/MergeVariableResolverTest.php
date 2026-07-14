<?php

namespace Tests\Unit\Modules\Composer;

use App\Modules\Composer\Services\MergeVariableResolver;
use App\Modules\Recipients\Models\Recipient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * docs/11-email-composer.md §11.4 — Merge Variables.
 */
class MergeVariableResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_resolves_recipient_fields(): void
    {
        $recipient = Recipient::factory()->make([
            'first_name' => 'Amine',
            'company_name' => 'Acme',
        ]);

        $resolver = new MergeVariableResolver();
        $result = $resolver->resolve('Bonjour {{recipient.first_name}} de {{recipient.company_name}}', $recipient);

        $this->assertSame('Bonjour Amine de Acme', $result);
    }

    public function test_it_falls_back_when_the_field_is_empty(): void
    {
        $recipient = Recipient::factory()->make(['first_name' => null]);

        $resolver = new MergeVariableResolver();
        $result = $resolver->resolve('Bonjour {{recipient.first_name|cher client}}', $recipient);

        $this->assertSame('Bonjour cher client', $result);
    }

    public function test_it_resolves_custom_fields(): void
    {
        $recipient = Recipient::factory()->make(['custom_fields' => ['industry' => 'retail']]);

        $resolver = new MergeVariableResolver();
        $result = $resolver->resolve('Secteur : {{recipient.custom_fields.industry}}', $recipient);

        $this->assertSame('Secteur : retail', $result);
    }

    public function test_it_resolves_the_unsubscribe_url_system_variable(): void
    {
        $recipient = Recipient::factory()->make();

        $resolver = new MergeVariableResolver();
        $result = $resolver->resolve('{{unsubscribe_url}}', $recipient, 'https://example.com/unsubscribe/abc');

        $this->assertSame('https://example.com/unsubscribe/abc', $result);
    }

    public function test_it_extracts_variable_names(): void
    {
        $resolver = new MergeVariableResolver();
        $names = $resolver->extractVariableNames('{{recipient.first_name}} - {{recipient.custom_fields.industry|N/A}}');

        $this->assertSame(['recipient.first_name', 'recipient.custom_fields.industry'], $names);
    }
}
