<?php

namespace Tests\Feature\Modules\Webhooks;

use App\Domain\Enums\RoleName;
use App\Modules\Identity\Models\User;

class WebhookWebRouteTest extends WebhookTestCase
{
    public function test_logs_page_renders_for_transactional_viewers(): void
    {
        foreach ([$this->admin(), $this->manager()] as $user) {
            $this->actingAs($user)->get('/transactional/logs')->assertOk()
                ->assertSee('Transactional\/Logs', false);
        }
        $this->assertSame('/transactional/logs', route('transactional.logs', absolute: false));
    }

    public function test_logs_page_is_forbidden_without_permission(): void
    {
        $operator = User::factory()->withRole(RoleName::MarketingOperator)->withTwoFactor()->create();
        $viewer = User::factory()->withRole(RoleName::Viewer)->withTwoFactor()->create();

        $this->actingAs($operator)->get('/transactional/logs')->assertForbidden();
        $this->actingAs($viewer)->get('/transactional/logs')->assertForbidden();
    }
}
