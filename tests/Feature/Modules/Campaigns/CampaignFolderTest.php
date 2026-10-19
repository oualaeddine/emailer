<?php

namespace Tests\Feature\Modules\Campaigns;

use App\Domain\Enums\RoleName;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\CampaignFolder;
use App\Modules\Identity\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Modules\Campaigns\Concerns\BuildsCampaignFixtures;
use Tests\TestCase;

/**
 * docs/45 §45.3 `Folder` / §45.4.2 `/campaign-folders` + move.
 */
class CampaignFolderTest extends TestCase
{
    use BuildsCampaignFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_folder_crud(): void
    {
        $user = $this->operator();

        $id = $this->actingAs($user)->postJson('/api/v1/campaign-folders', ['name' => 'Newsletters'])
            ->assertCreated()
            ->assertJsonPath('data.kind', 'campaign')
            ->assertJsonPath('data.items_count', 0)
            ->json('data.id');

        $this->actingAs($user)->postJson('/api/v1/campaign-folders', ['name' => 'Newsletters'])->assertStatus(422);
        $this->actingAs($user)->postJson('/api/v1/campaign-folders', ['name' => str_repeat('a', 101)])->assertStatus(422);

        $folder = CampaignFolder::query()->where('uuid', $id)->first();
        Campaign::factory()->count(2)->create(['folder_id' => $folder->id]);

        $this->actingAs($user)->getJson('/api/v1/campaign-folders')
            ->assertOk()->assertJsonPath('data.0.name', 'Newsletters')->assertJsonPath('data.0.items_count', 2);

        $this->actingAs($user)->patchJson("/api/v1/campaign-folders/{$id}", ['name' => 'Promos'])
            ->assertOk()->assertJsonPath('data.name', 'Promos');
        $this->actingAs($user)->patchJson("/api/v1/campaign-folders/{$id}", ['name' => 'Promos'])->assertOk();

        $this->actingAs($user)->deleteJson("/api/v1/campaign-folders/{$id}")->assertNoContent();
        $this->assertSame(2, Campaign::query()->whereNull('folder_id')->count());
    }

    public function test_viewer_can_list_but_not_write(): void
    {
        $viewer = User::factory()->withRole(RoleName::Viewer)->create();
        $folder = CampaignFolder::factory()->create();

        $this->actingAs($viewer)->getJson('/api/v1/campaign-folders')->assertOk();
        $this->actingAs($viewer)->postJson('/api/v1/campaign-folders', ['name' => 'X'])->assertForbidden();
        $this->actingAs($viewer)->deleteJson("/api/v1/campaign-folders/{$folder->uuid}")->assertForbidden();
    }

    public function test_move_campaign_into_folder_via_patch(): void
    {
        $user = $this->operator();
        $folder = CampaignFolder::factory()->create();
        $campaign = Campaign::factory()->create(['created_by' => $user->id]);

        $this->actingAs($user)->patchJson("/api/v1/campaigns/{$campaign->uuid}", ['folder_id' => $folder->uuid])
            ->assertOk()->assertJsonPath('data.folder_id', $folder->uuid);
        $this->actingAs($user)->patchJson("/api/v1/campaigns/{$campaign->uuid}", ['folder_id' => null])
            ->assertOk()->assertJsonPath('data.folder_id', null);
    }
}
