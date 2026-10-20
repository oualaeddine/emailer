<?php

namespace Tests\Feature\Modules\Suppression\Preferences;

use App\Modules\Settings\Services\SettingsService;
use App\Modules\Suppression\Preferences\Models\UnsubscribePage;
use App\Modules\Suppression\Preferences\Models\UpdateProfileForm;
use Database\Seeders\DefaultPreferencePagesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DefaultPreferencePagesSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_data_migration_already_seeded_defaults(): void
    {
        // RefreshDatabase ran slot M's data migration.
        $this->assertSame(1, UnsubscribePage::where('is_default', true)->count());
        $this->assertSame(1, UpdateProfileForm::where('is_default', true)->count());
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(DefaultPreferencePagesSeeder::class);
        $this->seed(DefaultPreferencePagesSeeder::class);

        $this->assertSame(1, UnsubscribePage::count());
        $this->assertSame(1, UpdateProfileForm::count());
        $this->assertSame(1, DB::table('templates')->where('scope', 'transactional')->where('name', DefaultPreferencePagesSeeder::DOI_TEMPLATE_NAME)->count());

        $uuid = app(SettingsService::class)->get(DefaultPreferencePagesSeeder::DOI_SETTING);
        $this->assertTrue(DB::table('templates')->where('uuid', $uuid)->exists());
    }

    public function test_seeder_restores_a_default_when_none_is_flagged(): void
    {
        UnsubscribePage::query()->update(['is_default' => false]);

        $this->seed(DefaultPreferencePagesSeeder::class);

        $this->assertSame(1, UnsubscribePage::where('is_default', true)->count());
    }
}
