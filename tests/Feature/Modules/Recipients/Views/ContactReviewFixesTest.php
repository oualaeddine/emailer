<?php

namespace Tests\Feature\Modules\Recipients\Views;

use App\Domain\Enums\RoleName;
use App\Domain\Enums\SuppressionReason;
use App\Modules\Identity\Models\User;
use App\Modules\Recipients\Attributes\Events\ContactAttributesUpdated;
use App\Modules\Recipients\Attributes\Models\ContactAttribute;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Recipients\Views\Models\AsyncOperation;
use App\Modules\Suppression\Models\SuppressionEntry;
use App\Modules\Suppression\Services\SuppressionManager;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Wave 2 review fixes on the contacts table / bulk / export / detail API
 * (B5, S2, S3, S11, S13, N3, N4, N8).
 */
class ContactReviewFixesTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');
        $this->manager = User::factory()->withRole(RoleName::MarketingManager)->create();
    }

    public function test_bulk_unblocklist_only_lifts_manual_blocks(): void
    {
        $manual = Recipient::factory()->create(['email' => 'manual@x.dz']);
        $bounced = Recipient::factory()->create(['email' => 'bounce@x.dz']);
        $suppression = app(SuppressionManager::class);
        $suppression->suppress('manual@x.dz', SuppressionReason::ManualBlock);
        $suppression->suppress('bounce@x.dz', SuppressionReason::HardBounce);
        $this->assertNotNull($manual->fresh()->getAttribute('blocklisted_at'));

        $this->actingAs($this->manager)->postJson('/api/v1/recipients/bulk', [
            'action' => 'unblocklist', 'selection' => ['ids' => [$manual->uuid, $bounced->uuid]],
        ])->assertOk()
            ->assertJsonPath('data.processed', 1)
            ->assertJsonPath('data.skipped.0.id', $bounced->uuid)
            ->assertJsonPath('data.skipped.0.reason', 'Bloqué pour une autre raison (hard_bounce)');

        $this->assertFalse(SuppressionEntry::query()->where('email', 'manual@x.dz')->exists());
        $this->assertTrue(SuppressionEntry::query()->where('email', 'bounce@x.dz')->where('reason', 'hard_bounce')->exists());
        $this->assertSame('active', $manual->fresh()->status);
        $this->assertNull($manual->fresh()->getAttribute('blocklisted_at')); // mirror listener ran
        $this->assertSame('suppressed', $bounced->fresh()->status);
        $this->assertNotNull($bounced->fresh()->getAttribute('blocklisted_at'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'contacts.bulk_unblocklisted', 'user_id' => $this->manager->id]);
    }

    public function test_blocklisted_column_is_accepted_but_not_sortable(): void
    {
        $blocked = Recipient::factory()->suppressed()->create(['email' => 'b@x.dz']);
        Recipient::factory()->create(['email' => 'a@x.dz']);

        $r = $this->actingAs($this->manager)->getJson('/api/v1/recipients?pagination=cursor&sort=attribute.EMAIL&columns=attribute.EMAIL,contact.blocklisted')
            ->assertOk()->assertJsonPath('meta.columns', ['attribute.EMAIL', 'contact.blocklisted']);
        $this->assertSame([false, true], array_column($r->json('data'), 'blocklisted'));
        $this->assertSame($blocked->uuid, $r->json('data.1.id'));

        $this->actingAs($this->manager)->getJson('/api/v1/recipients?pagination=cursor&sort=contact.blocklisted')
            ->assertStatus(422)->assertJsonValidationErrors('sort');
    }

    public function test_column_stored_attribute_is_read_from_its_column(): void
    {
        $r = Recipient::factory()->create(['email' => 'ext@x.dz', 'custom_fields' => ['EXT_ID' => 'stale']]);
        $r->forceFill(['ext_id' => 'CRM-42'])->save();

        $this->actingAs($this->manager)->getJson('/api/v1/recipients?pagination=cursor&columns=attribute.EXT_ID&sort=-attribute.EXT_ID')
            ->assertOk()->assertJsonPath('data.0.attributes.EXT_ID', 'CRM-42');
    }

    public function test_number_and_date_attributes_sort_by_value(): void
    {
        ContactAttribute::factory()->create(['name' => 'AGE', 'type' => 'number']);
        ContactAttribute::factory()->create(['name' => 'NAISSANCE', 'type' => 'date']);
        foreach ([['a', 9, '1990-05-01'], ['b', 10, '1985-12-31'], ['c', 100, '2001-01-15']] as [$n, $age, $date]) {
            Recipient::factory()->create(['email' => "$n@x.dz", 'custom_fields' => ['AGE' => $age, 'NAISSANCE' => $date]]);
        }

        $byAge = $this->actingAs($this->manager)->getJson('/api/v1/recipients?pagination=cursor&sort=-attribute.AGE')->assertOk();
        $this->assertSame(['c@x.dz', 'b@x.dz', 'a@x.dz'], array_column($byAge->json('data'), 'email'));

        $byDate = $this->actingAs($this->manager)->getJson('/api/v1/recipients?pagination=cursor&sort=attribute.NAISSANCE')->assertOk();
        $this->assertSame(['b@x.dz', 'a@x.dz', 'c@x.dz'], array_column($byDate->json('data'), 'email'));
    }

    public function test_search_escapes_wildcards_and_ignores_case(): void
    {
        Recipient::factory()->create(['email' => 'promo_100@x.dz', 'first_name' => 'Amina']);
        Recipient::factory()->create(['email' => 'promoX100@x.dz', 'first_name' => 'Karim']);

        $this->actingAs($this->manager)->getJson('/api/v1/recipients?pagination=cursor&q='.urlencode('promo_1'))
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.email', 'promo_100@x.dz');
        $this->actingAs($this->manager)->getJson('/api/v1/recipients?pagination=cursor&q=%25')
            ->assertOk()->assertJsonPath('meta.total', 0);
        $this->actingAs($this->manager)->getJson('/api/v1/recipients?pagination=cursor&q=AMINA')
            ->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_export_neutralizes_formula_cells(): void
    {
        Recipient::factory()->create(['email' => 'f@x.dz', 'first_name' => '=HYPERLINK("http://evil")', 'last_name' => '-2+3', 'company_name' => '@SUM(A1)']);

        $this->actingAs($this->manager)->postJson('/api/v1/recipients/export', [
            'selection' => ['all_matching' => ['scope' => 'all']],
            'columns' => ['attribute.EMAIL', 'attribute.PRENOM', 'attribute.NOM', 'attribute.SOCIETE'],
            'format' => 'csv',
        ])->assertStatus(202);

        $op = AsyncOperation::query()->firstOrFail();
        $content = Storage::disk('local')->get($op->payload['path']);
        $this->assertStringContainsString('f@x.dz;"\'=HYPERLINK(""http://evil"")";\'-2+3;\'@SUM(A1)', $content);
    }

    public function test_patch_merges_custom_fields_and_writes_attributes_through_the_service(): void
    {
        ContactAttribute::factory()->create(['name' => 'AGE', 'type' => 'number']);
        $recipient = Recipient::factory()->create(['custom_fields' => ['VILLE' => 'oran', 'SECTEUR' => 'btp']]);
        Event::fake([ContactAttributesUpdated::class]);

        $this->actingAs($this->manager)->patchJson('/api/v1/recipients/'.$recipient->uuid, [
            'custom_fields' => ['VILLE' => 'alger', 'SECTEUR' => null],
            'attributes' => ['AGE' => '42'],
            'ext_id' => 'CRM-7',
        ])->assertOk()
            ->assertJsonPath('data.ext_id', 'CRM-7')
            ->assertJsonPath('data.custom_fields.VILLE', 'alger')
            ->assertJsonPath('data.custom_fields.AGE', 42);

        $fresh = $recipient->fresh();
        $this->assertSame(['VILLE' => 'alger', 'AGE' => 42], $fresh->custom_fields);
        $this->assertSame('CRM-7', $fresh->getAttribute('ext_id'));
        Event::assertDispatched(ContactAttributesUpdated::class, fn ($e) => $e->recipientId === $recipient->id
            && $e->source === 'manual' && isset($e->changes['AGE'], $e->changes['EXT_ID']));

        $this->actingAs($this->manager)->patchJson('/api/v1/recipients/'.$recipient->uuid, ['attributes' => ['AGE' => 'abc']])
            ->assertStatus(422)->assertJsonValidationErrors('attributes.AGE');
        $this->actingAs($this->manager)->patchJson('/api/v1/recipients/'.$recipient->uuid, ['attributes' => ['INCONNU' => 'x']])
            ->assertStatus(422);
        $other = Recipient::factory()->create();
        $this->actingAs($this->manager)->patchJson('/api/v1/recipients/'.$other->uuid, ['ext_id' => 'CRM-7'])
            ->assertStatus(422)->assertJsonValidationErrors('ext_id');
    }

    public function test_show_exposes_engagement_and_creator_fields(): void
    {
        $recipient = Recipient::factory()->create();
        $recipient->forceFill([
            'ext_id' => 'E1', 'opt_in_at' => '2026-01-02 03:04:05', 'opt_in_source' => 'form',
            'last_opened_at' => '2026-02-01 10:00:00', 'engagement_score' => 73, 'created_by' => $this->manager->id,
        ])->save();

        $this->actingAs($this->manager)->getJson('/api/v1/recipients/'.$recipient->uuid)
            ->assertOk()
            ->assertJsonPath('data.ext_id', 'E1')
            ->assertJsonPath('data.opt_in_source', 'form')
            ->assertJsonPath('data.engagement_score', 73)
            ->assertJsonPath('data.last_clicked_at', null)
            ->assertJsonPath('data.created_by.id', $this->manager->uuid)
            ->assertJsonPath('data.created_by.name', $this->manager->name)
            ->assertJsonStructure(['data' => ['opt_in_at', 'last_opened_at', 'pagejaunes_external_id']]);
    }
}
