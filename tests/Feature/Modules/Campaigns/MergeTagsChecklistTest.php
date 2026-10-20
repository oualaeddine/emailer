<?php

namespace Tests\Feature\Modules\Campaigns;

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Services\Checklist\CampaignChecklistContract;
use App\Modules\Recipients\Attributes\Models\ContactAttribute;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Modules\Campaigns\Concerns\BuildsCampaignFixtures;
use Tests\TestCase;

/**
 * docs/45 §45.4.5 — `merge_tags` checklist item (next-tags).
 */
class MergeTagsChecklistTest extends TestCase
{
    use BuildsCampaignFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function ready(array $attributes = []): Campaign
    {
        return $this->campaignWithAudience($this->operator(), ['lists' => [$this->listWith($this->recipients(2))->id]], $attributes);
    }

    private function item(Campaign $campaign)
    {
        return app(CampaignChecklistContract::class)->evaluate($campaign)->item('merge_tags');
    }

    public function test_unknown_tag_in_subject_is_a_blocking_error(): void
    {
        $campaign = $this->ready(['subject' => 'Salut {{attribute.PRENOM}} et {{first_name}}']);

        $item = $this->item($campaign);
        $this->assertSame('error', $item->status);
        $this->assertTrue($item->blocking);
        $this->assertSame('Balises inconnues : {{attribute.PRENOM}}, {{first_name}}', $item->message);
        $this->assertSame(['{{attribute.PRENOM}}', '{{first_name}}'], $item->details['unknown']);
        $this->assertContains('merge_tags', app(CampaignChecklistContract::class)->blockingFailures($campaign));
    }

    public function test_supported_tags_are_ok(): void
    {
        ContactAttribute::factory()->ofType('text')->create(['name' => 'VILLE']);

        $campaign = $this->ready([
            'subject' => '{{ campaign.NAME }} {{ contact.PRENOM | default:"cher client" }}',
            'preview_text' => '{{ contact.ville }} {{ mirror }}',
            'html_body' => '<p>{{recipient.first_name|there}} {{ sender.name }} {{ update_profile }} {{ recipient.custom_fields.x }}</p>'
                .'<a href="{{ unsubscribe }}">u</a> {{unsubscribe_url}} {{ current_date }}',
        ]);

        $item = $this->item($campaign);
        $this->assertSame('ok', $item->status, $item->message);
    }

    public function test_tags_in_html_body_and_to_field_are_detected(): void
    {
        $campaign = $this->ready([
            'html_body' => '<p>{{params.foo}}</p><a href="{{ unsubscribe }}">u</a>',
            'to_field' => '{{ contact.INCONNU }}',
        ]);

        $item = $this->item($campaign);
        $this->assertSame('error', $item->status);
        $this->assertSame(['{{params.foo}}', '{{ contact.INCONNU }}'], $item->details['unknown']);
    }

    public function test_message_lists_at_most_five_tags(): void
    {
        $campaign = $this->ready(['subject' => '{{a}} {{b}} {{c}} {{d}} {{e}} {{f}}']);

        $item = $this->item($campaign);
        $this->assertSame('Balises inconnues : {{a}}, {{b}}, {{c}}, {{d}}, {{e}} (+1)', $item->message);
        $this->assertCount(6, $item->details['unknown']);
    }

    public function test_schedule_is_refused_when_a_merge_tag_is_unknown(): void
    {
        $user = $this->operator();
        $campaign = $this->campaignWithAudience($user, ['lists' => [$this->listWith($this->recipients(2))->id]], ['subject' => 'Bonjour {{first_name}}']);

        $this->actingAs($user)->postJson("/api/v1/campaigns/{$campaign->uuid}/schedule", ['mode' => 'now'])
            ->assertStatus(422)
            ->assertJsonPath('errors.checklist', ['merge_tags']);

        $this->assertSame('draft', $campaign->fresh()->status);
    }
}
