<?php

namespace Tests\Unit\Modules\Composer;

use App\Modules\Composer\Services\MergeVariableResolver;
use App\Modules\Recipients\Attributes\Models\ContactAttribute;
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

    public function test_it_resolves_ui_contact_aliases_case_insensitively(): void
    {
        $recipient = Recipient::factory()->make([
            'first_name' => 'Amine', 'last_name' => 'Benali', 'email' => 'a@b.dz', 'company_name' => 'Acme',
        ]);

        $result = (new MergeVariableResolver)->resolve(
            '{{ contact.PRENOM }} {{contact.nom}} <{{ contact.EMAIL }}> {{ contact.Societe }} {{ contact.UNKNOWN|x }}',
            $recipient,
        );

        $this->assertSame('Amine Benali <a@b.dz> Acme x', $result);
    }

    public function test_it_supports_the_default_filter(): void
    {
        $recipient = Recipient::factory()->make(['first_name' => null]);
        $resolver = new MergeVariableResolver;

        $this->assertSame('Bonjour cher client', $resolver->resolve('Bonjour {{ contact.PRENOM | default:"cher client" }}', $recipient));
        $this->assertSame('Bonjour ami', $resolver->resolve("Bonjour {{ contact.PRENOM|default:'ami' }}", $recipient));
        $this->assertSame('Amine', $resolver->resolve('{{ contact.PRENOM | default:"x" }}', Recipient::factory()->make(['first_name' => 'Amine'])));
    }

    public function test_it_resolves_every_attribute_definition_by_type(): void
    {
        $opts = [['value' => 'alger', 'label' => 'Alger'], ['value' => 'oran', 'label' => 'Oran']];
        ContactAttribute::factory()->ofType('category', $opts)->create(['name' => 'VILLE']);
        ContactAttribute::factory()->ofType('multiple_choice', $opts)->create(['name' => 'ZONES']);
        ContactAttribute::factory()->ofType('boolean')->create(['name' => 'VIP']);
        ContactAttribute::factory()->ofType('date')->create(['name' => 'NAISSANCE']);
        ContactAttribute::factory()->ofType('number')->create(['name' => 'AGE']);

        $recipient = Recipient::factory()->make([
            'first_name' => 'Amine',
            'custom_fields' => [
                'VILLE' => 'oran', 'ZONES' => ['alger', 'oran'], 'VIP' => true, 'NAISSANCE' => '1990-02-03', 'AGE' => 34,
            ],
        ]);

        $result = (new MergeVariableResolver)->resolve(
            '{{ contact.ville }}|{{ contact.ZONES }}|{{ contact.VIP }}|{{ contact.naissance }}|{{ contact.AGE }}|{{ contact.PRENOM }}|{{ contact.MISSING | default:"-" }}',
            $recipient,
        );

        $this->assertSame('Oran|Alger, Oran|Oui|03/02/1990|34|Amine|-', $result);
    }

    public function test_it_resolves_the_unsubscribe_alias_and_spaced_filters_in_extraction(): void
    {
        $resolver = new MergeVariableResolver;

        $this->assertSame('https://u', $resolver->resolve('{{ unsubscribe }}', Recipient::factory()->make(), 'https://u'));
        $this->assertSame(['contact.PRENOM'], $resolver->extractVariableNames('{{ contact.PRENOM | default:"x" }}'));
    }
}
