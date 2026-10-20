<?php

namespace Tests\Feature\Modules\Recipients\Attributes;

use App\Domain\Enums\RoleName;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Identity\Models\User;
use App\Modules\Recipients\Attributes\Contracts\ContactAttributeService;
use App\Modules\Recipients\Attributes\Events\ContactAttributesUpdated;
use App\Modules\Recipients\Attributes\Models\ContactAttribute;
use App\Modules\Recipients\Models\Recipient;
use Database\Seeders\ContactAttributeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** docs/45 §45.5.1 — WP-50 contact attributes. */
class ContactAttributeApiTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->manager = User::factory()->withRole(RoleName::MarketingManager)->create();
    }

    private function viewer(): User
    {
        return User::factory()->withRole(RoleName::Viewer)->create();
    }

    private function operator(): User
    {
        return User::factory()->withRole(RoleName::MarketingOperator)->create();
    }

    public function test_system_attributes_are_seeded_and_the_seeder_is_idempotent(): void
    {
        $this->assertSame(6, ContactAttribute::query()->where('is_system', true)->count());
        (new ContactAttributeSeeder)->run();
        (new ContactAttributeSeeder)->run();
        $this->assertSame(6, ContactAttribute::query()->count());
        $this->assertSame('first_name', ContactAttribute::query()->where('name', 'PRENOM')->value('column'));
    }

    public function test_list_returns_system_first_and_usage_on_demand(): void
    {
        ContactAttribute::factory()->create(['name' => 'ZED']);
        Recipient::factory()->create(['custom_fields' => ['ZED' => 'x']]);
        Recipient::factory()->create(['custom_fields' => []]);

        $r = $this->actingAs($this->viewer())->getJson('/api/v1/contact-attributes')->assertOk();
        $this->assertTrue($r->json('data.0.is_system'));
        $this->assertSame('ZED', collect($r->json('data'))->last()['name']);
        $this->assertNull($r->json('data.0.usage_count'));

        $r = $this->actingAs($this->viewer())->getJson('/api/v1/contact-attributes?include_usage=1')->assertOk();
        $zed = collect($r->json('data'))->firstWhere('name', 'ZED');
        $this->assertSame(1, $zed['usage_count']);
        $this->assertSame('{{ contact.ZED }}', $zed['merge_tag']);
    }

    public function test_create_requires_the_manage_permission(): void
    {
        $payload = ['name' => 'VILLE', 'type' => 'text'];
        $this->actingAs($this->viewer())->postJson('/api/v1/contact-attributes', $payload)->assertForbidden();
        $this->actingAs($this->operator())->postJson('/api/v1/contact-attributes', $payload)->assertForbidden();
        $this->actingAs($this->manager)->postJson('/api/v1/contact-attributes', $payload)->assertCreated()
            ->assertJsonPath('data.name', 'VILLE')
            ->assertJsonPath('data.label', 'VILLE')
            ->assertJsonPath('data.is_system', false)
            ->assertJsonPath('data.storage', 'custom_fields')
            ->assertJsonPath('data.options', []);
        $this->assertTrue(AuditLog::query()->where('action', 'attribute.created')->exists());
    }

    public function test_name_is_uppercased_and_validated(): void
    {
        $this->actingAs($this->manager)->postJson('/api/v1/contact-attributes', ['name' => 'ville_2', 'type' => 'text'])
            ->assertCreated()->assertJsonPath('data.name', 'VILLE_2');

        foreach (['BAD NAME', 'ÉTÉ', '1ABC', str_repeat('A', 51), ''] as $bad) {
            $this->actingAs($this->manager)->postJson('/api/v1/contact-attributes', ['name' => $bad, 'type' => 'text'])
                ->assertStatus(422)->assertJsonValidationErrors('name');
        }

        $this->actingAs($this->manager)->postJson('/api/v1/contact-attributes', ['name' => str_repeat('A', 50), 'type' => 'text'])->assertCreated();
        $this->actingAs($this->manager)->postJson('/api/v1/contact-attributes', ['name' => 'VILLE_2', 'type' => 'text'])
            ->assertStatus(422)->assertJsonValidationErrors('name');
        $this->actingAs($this->manager)->postJson('/api/v1/contact-attributes', ['name' => 'PRENOM', 'type' => 'text'])
            ->assertStatus(422);
        $this->actingAs($this->manager)->postJson('/api/v1/contact-attributes', ['name' => 'OTHER', 'type' => 'color'])
            ->assertStatus(422)->assertJsonValidationErrors('type');
    }

    public function test_choice_types_require_options_and_other_types_forbid_them(): void
    {
        foreach (['category', 'multiple_choice'] as $type) {
            $this->actingAs($this->manager)->postJson('/api/v1/contact-attributes', ['name' => 'C_'.$type, 'type' => $type])
                ->assertStatus(422)->assertJsonValidationErrors('options');
            $this->actingAs($this->manager)->postJson('/api/v1/contact-attributes', [
                'name' => 'C_'.$type, 'type' => $type, 'options' => [['value' => 'a', 'label' => 'A'], ['value' => 'a', 'label' => 'B']],
            ])->assertStatus(422);
            $this->actingAs($this->manager)->postJson('/api/v1/contact-attributes', [
                'name' => 'C_'.$type, 'type' => $type, 'options' => [['value' => 'a', 'label' => 'A']],
            ])->assertCreated()->assertJsonPath('data.options.0.value', 'a');
        }
        $this->actingAs($this->manager)->postJson('/api/v1/contact-attributes', [
            'name' => 'T', 'type' => 'text', 'options' => [['value' => 'a', 'label' => 'A']],
        ])->assertStatus(422);
    }

    public function test_the_200_attribute_cap_is_enforced(): void
    {
        $existing = ContactAttribute::query()->count();
        ContactAttribute::factory()->count(200 - $existing)->create();
        $this->assertSame(200, ContactAttribute::query()->count());

        $this->actingAs($this->manager)->postJson('/api/v1/contact-attributes', ['name' => 'ONE_TOO_MANY', 'type' => 'text'])
            ->assertStatus(422)->assertJsonValidationErrors('name');
    }

    public function test_show_and_update(): void
    {
        $a = ContactAttribute::factory()->choice()->create(['name' => 'VILLE']);

        $this->actingAs($this->viewer())->getJson("/api/v1/contact-attributes/{$a->uuid}")->assertOk()->assertJsonPath('data.name', 'VILLE');
        $this->actingAs($this->viewer())->patchJson("/api/v1/contact-attributes/{$a->uuid}", ['label' => 'X'])->assertForbidden();

        $this->actingAs($this->manager)->patchJson("/api/v1/contact-attributes/{$a->uuid}", [
            'label' => 'Ville', 'options' => [['value' => 'alger', 'label' => 'Alger'], ['value' => 'oran', 'label' => 'Oran'], ['value' => 'blida', 'label' => 'Blida']],
        ])->assertOk()->assertJsonPath('data.label', 'Ville')->assertJsonCount(3, 'data.options');

        $this->actingAs($this->manager)->patchJson("/api/v1/contact-attributes/{$a->uuid}", ['name' => 'AUTRE'])->assertStatus(422);
        $this->actingAs($this->manager)->patchJson("/api/v1/contact-attributes/{$a->uuid}", ['type' => 'text'])->assertStatus(422);
    }

    public function test_removing_an_option_in_use_is_a_conflict(): void
    {
        $a = ContactAttribute::factory()->choice()->create(['name' => 'VILLE']);
        Recipient::factory()->create(['custom_fields' => ['VILLE' => 'oran']]);

        $this->actingAs($this->manager)->patchJson("/api/v1/contact-attributes/{$a->uuid}", [
            'options' => [['value' => 'alger', 'label' => 'Alger']],
        ])->assertStatus(409);
        $this->actingAs($this->manager)->patchJson("/api/v1/contact-attributes/{$a->uuid}", [
            'options' => [['value' => 'oran', 'label' => 'Oran']],
        ])->assertOk();
    }

    public function test_system_attributes_cannot_be_edited_or_deleted(): void
    {
        $prenom = ContactAttribute::query()->where('name', 'PRENOM')->firstOrFail();

        $this->actingAs($this->manager)->patchJson("/api/v1/contact-attributes/{$prenom->uuid}", ['label' => 'X'])->assertStatus(409);
        $this->actingAs($this->manager)->deleteJson("/api/v1/contact-attributes/{$prenom->uuid}", ['confirm' => true])->assertStatus(409);
        $this->assertDatabaseHas('contact_attributes', ['name' => 'PRENOM']);
    }

    public function test_delete_requires_confirmation_audits_and_purges_values(): void
    {
        $a = ContactAttribute::factory()->create(['name' => 'TEMP']);
        $r = Recipient::factory()->create(['custom_fields' => ['TEMP' => 'v', 'keep' => 'k']]);

        $this->actingAs($this->viewer())->deleteJson("/api/v1/contact-attributes/{$a->uuid}", ['confirm' => true])->assertForbidden();
        $this->actingAs($this->manager)->deleteJson("/api/v1/contact-attributes/{$a->uuid}")->assertStatus(422);
        $this->actingAs($this->manager)->deleteJson("/api/v1/contact-attributes/{$a->uuid}", ['confirm' => true])->assertNoContent();

        $this->assertDatabaseMissing('contact_attributes', ['name' => 'TEMP']);
        $this->assertTrue(AuditLog::query()->where('action', 'attribute.deleted')->exists());
        $this->assertSame(['keep' => 'k'], $r->fresh()->custom_fields); // queue = sync in tests
    }

    public function test_deleting_an_attribute_used_by_a_segment_is_a_conflict(): void
    {
        $a = ContactAttribute::factory()->create(['name' => 'VILLE']);
        $list = \App\Modules\Recipients\Models\RecipientList::query()->create(['name' => 'Seg', 'type' => 'segment']);
        \App\Modules\Recipients\Models\Segment::query()->create([
            'recipient_list_id' => $list->id,
            'rules' => ['field' => 'VILLE', 'operator' => 'equals', 'value' => 'oran'],
        ]);

        $this->actingAs($this->manager)->deleteJson("/api/v1/contact-attributes/{$a->uuid}", ['confirm' => true])
            ->assertStatus(409)->assertJsonPath('errors.used_in.0.type', 'segment');
        $this->assertDatabaseHas('contact_attributes', ['name' => 'VILLE']);
    }

    public function test_merge_tags_endpoint(): void
    {
        ContactAttribute::factory()->create(['name' => 'VILLE', 'label' => 'Ville']);

        $r = $this->actingAs($this->viewer())->getJson('/api/v1/contact-attributes/merge-tags')->assertOk();
        $tags = collect($r->json('data'));
        $this->assertSame(
            ['tag' => '{{ contact.PRENOM }}', 'label' => 'Prénom', 'group' => 'contact', 'attribute' => 'PRENOM'],
            $tags->firstWhere('attribute', 'PRENOM'),
        );
        $this->assertNotNull($tags->firstWhere('tag', '{{ contact.VILLE }}'));
        foreach (['{{ mirror }}', '{{ unsubscribe }}', '{{ update_profile }}'] as $tag) {
            $this->assertSame('links', $tags->firstWhere('tag', $tag)['group']);
        }
        foreach (['{{ campaign.NAME }}', '{{ campaign.SUBJECT }}', '{{ sender.NAME }}', '{{ sender.EMAIL }}'] as $tag) {
            $this->assertSame('campaign', $tags->firstWhere('tag', $tag)['group']);
        }
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/contact-attributes')->assertUnauthorized();
    }

    // --- values on recipients ------------------------------------------------------------

    private function defineAll(): void
    {
        $opts = [['value' => 'a', 'label' => 'A'], ['value' => 'b', 'label' => 'B']];
        foreach ([
            ['N', 'number'], ['D', 'date'], ['B', 'boolean'], ['T', 'text'],
            ['CAT', 'category', $opts], ['MULTI', 'multiple_choice', $opts], ['OWNER', 'user'],
        ] as $row) {
            ContactAttribute::factory()->ofType($row[1], $row[2] ?? null)->create(['name' => $row[0]]);
        }
    }

    public function test_recipient_create_casts_valid_values_per_type(): void
    {
        $this->defineAll();
        $owner = User::factory()->create();

        $this->actingAs($this->operator())->postJson('/api/v1/recipients', [
            'email' => 'v@example.com', 'source' => 'manual',
            'custom_fields' => [
                'N' => '12.5', 'D' => '25/12/2026', 'B' => 'oui', 'T' => 'bonjour', 'CAT' => 'a',
                'MULTI' => ['a', 'b', 'a'], 'OWNER' => $owner->uuid, 'legacy' => 'untouched',
            ],
        ])->assertCreated();

        $fields = Recipient::query()->where('email', 'v@example.com')->firstOrFail()->custom_fields;
        $this->assertSame(12.5, $fields['N']);
        $this->assertSame('2026-12-25', $fields['D']);
        $this->assertTrue($fields['B']);
        $this->assertSame('bonjour', $fields['T']);
        $this->assertSame('a', $fields['CAT']);
        $this->assertSame(['a', 'b'], $fields['MULTI']);
        $this->assertSame($owner->uuid, $fields['OWNER']);
        $this->assertSame('untouched', $fields['legacy']);
    }

    public function test_recipient_create_returns_422_keyed_by_attribute_for_each_invalid_type(): void
    {
        $this->defineAll();

        foreach ([
            'N' => 'abc', 'D' => '2026-13-45', 'B' => 'peut-être', 'CAT' => 'zzz', 'MULTI' => ['a', 'zzz'], 'OWNER' => 'not-a-user', 'T' => ['x'],
        ] as $name => $bad) {
            $this->actingAs($this->operator())->postJson('/api/v1/recipients', [
                'email' => strtolower($name).'@example.com', 'source' => 'manual', 'custom_fields' => [$name => $bad],
            ])->assertStatus(422)->assertJsonValidationErrors("attributes.$name");
        }
        $this->assertSame(0, Recipient::query()->count());
    }

    public function test_recipient_update_casts_and_validates(): void
    {
        $this->defineAll();
        $recipient = Recipient::factory()->create();

        $this->actingAs($this->operator())->patchJson("/api/v1/recipients/{$recipient->uuid}", ['custom_fields' => ['N' => '7', 'B' => 'false']])->assertOk();
        $this->assertSameJson(['N' => 7, 'B' => false], $recipient->fresh()->custom_fields);

        $this->actingAs($this->operator())->patchJson("/api/v1/recipients/{$recipient->uuid}", ['custom_fields' => ['N' => 'x']])
            ->assertStatus(422)->assertJsonValidationErrors('attributes.N');
    }

    public function test_apply_writes_columns_and_custom_fields_and_fires_event(): void
    {
        $this->defineAll();
        Event::fake([ContactAttributesUpdated::class]);
        $recipient = Recipient::factory()->create(['first_name' => 'Old', 'custom_fields' => []]);
        $service = app(ContactAttributeService::class);

        $changes = $service->apply($recipient, ['prenom' => 'Amine', 'N' => 3], 'test');

        $this->assertSame(['old' => 'Old', 'new' => 'Amine'], $changes['PRENOM']);
        $this->assertSame(['old' => null, 'new' => 3], $changes['N']);
        $this->assertSame('Amine', $recipient->fresh()->first_name);
        $this->assertSame(['N' => 3], $recipient->fresh()->custom_fields);
        Event::assertDispatched(ContactAttributesUpdated::class, fn ($e) => $e->recipientId === $recipient->id && $e->source === 'test');

        $this->assertSame([], $service->apply($recipient, ['N' => 3], 'test'));

        $this->expectException(ValidationException::class);
        $service->apply($recipient, ['UNKNOWN_ATTR' => 1], 'test');
    }

    public function test_apply_rejects_email_changes(): void
    {
        $recipient = Recipient::factory()->create();
        $this->expectException(ValidationException::class);
        app(ContactAttributeService::class)->apply($recipient, ['EMAIL' => 'x@y.dz'], 'test');
    }
}
