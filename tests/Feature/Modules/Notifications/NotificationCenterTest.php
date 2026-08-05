<?php

namespace Tests\Feature\Modules\Notifications;

use App\Domain\Enums\RoleName;
use App\Modules\Identity\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * docs/41-notification-center.md §41.5 — bell panel endpoints. Every
 * assertion here relies on the existing `Notifiable` relation
 * (`App\Modules\Identity\Models\User`) to scope rows to the authenticated
 * user, so notifications are seeded directly against the standard
 * `notifications` table rather than via a real event/listener dispatch.
 */
class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function seedNotification(User $user, bool $read = false): DatabaseNotification
    {
        return DatabaseNotification::create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Modules\\Identity\\Notifications\\RoleChangedNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => [
                'title' => 'Votre rôle a été modifié',
                'message' => 'Votre rôle est passé de « Lecteur » à « Opérateur marketing ».',
                'category' => 'account_security',
            ],
            'read_at' => $read ? now() : null,
        ]);
    }

    public function test_index_returns_only_the_authenticated_users_own_notifications(): void
    {
        $userA = User::factory()->withRole(RoleName::Viewer)->create();
        $userB = User::factory()->withRole(RoleName::Viewer)->create();

        $this->seedNotification($userA);
        $this->seedNotification($userB);
        $this->seedNotification($userB);

        $response = $this->actingAs($userA)->getJson('/api/v1/notifications');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.type', 'role_changed');
    }

    public function test_marking_a_notification_as_read_updates_read_at(): void
    {
        $user = User::factory()->withRole(RoleName::Viewer)->create();
        $notification = $this->seedNotification($user);

        $response = $this->actingAs($user)->patchJson("/api/v1/notifications/{$notification->id}/read");

        $response->assertOk();
        $response->assertJsonPath('data.id', $notification->id);
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_a_user_cannot_mark_another_users_notification_as_read(): void
    {
        $userA = User::factory()->withRole(RoleName::Viewer)->create();
        $userB = User::factory()->withRole(RoleName::Viewer)->create();
        $notification = $this->seedNotification($userB);

        $this->actingAs($userA)
            ->patchJson("/api/v1/notifications/{$notification->id}/read")
            ->assertNotFound();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_unread_count_is_accurate(): void
    {
        $user = User::factory()->withRole(RoleName::Viewer)->create();
        $this->seedNotification($user);
        $this->seedNotification($user);
        $this->seedNotification($user, read: true);

        $response = $this->actingAs($user)->getJson('/api/v1/notifications/unread-count');

        $response->assertOk();
        $response->assertJsonPath('count', 2);
    }

    public function test_unread_count_is_scoped_to_the_authenticated_user(): void
    {
        $userA = User::factory()->withRole(RoleName::Viewer)->create();
        $userB = User::factory()->withRole(RoleName::Viewer)->create();
        $this->seedNotification($userA);
        $this->seedNotification($userB);
        $this->seedNotification($userB);

        $response = $this->actingAs($userA)->getJson('/api/v1/notifications/unread-count');

        $response->assertOk();
        $response->assertJsonPath('count', 1);
    }

    public function test_mark_all_as_read_clears_the_unread_count(): void
    {
        $user = User::factory()->withRole(RoleName::Viewer)->create();
        $this->seedNotification($user);
        $this->seedNotification($user);

        $response = $this->actingAs($user)->postJson('/api/v1/notifications/mark-all-read');

        $response->assertOk();
        $response->assertJsonPath('count', 0);
        $this->assertSame(0, $user->unreadNotifications()->count());
    }

    public function test_mark_all_as_read_never_touches_another_users_notifications(): void
    {
        $userA = User::factory()->withRole(RoleName::Viewer)->create();
        $userB = User::factory()->withRole(RoleName::Viewer)->create();
        $this->seedNotification($userA);
        $notificationB = $this->seedNotification($userB);

        $this->actingAs($userA)->postJson('/api/v1/notifications/mark-all-read')->assertOk();

        $this->assertNull($notificationB->fresh()->read_at);
    }

    public function test_notification_preferences_default_to_enabled_for_a_new_user(): void
    {
        $user = User::factory()->withRole(RoleName::Viewer)->create();

        $response = $this->actingAs($user)->getJson('/api/v1/notification-preferences');

        $response->assertOk();
        $response->assertJsonCount(12, 'data');
        $response->assertJsonFragment([
            'category' => 'account_security',
            'channel' => 'in_app',
            'enabled' => true,
            'locked' => true,
        ]);
    }

    public function test_a_user_cannot_disable_in_app_account_security_notifications(): void
    {
        $user = User::factory()->withRole(RoleName::Viewer)->create();

        $response = $this->actingAs($user)->patchJson('/api/v1/notification-preferences', [
            'preferences' => [
                ['category' => 'account_security', 'channel' => 'in_app', 'enabled' => false],
            ],
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('notification_preferences', [
            'user_id' => $user->id,
            'category' => 'account_security',
            'channel' => 'in_app',
        ]);
    }

    public function test_a_user_can_disable_email_notifications_for_campaign_activity(): void
    {
        $user = User::factory()->withRole(RoleName::Viewer)->create();

        $response = $this->actingAs($user)->patchJson('/api/v1/notification-preferences', [
            'preferences' => [
                ['category' => 'campaign_activity', 'channel' => 'email', 'enabled' => false],
            ],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $user->id,
            'category' => 'campaign_activity',
            'channel' => 'email',
            'enabled' => false,
        ]);
    }

    public function test_notification_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/notifications')->assertUnauthorized();
        $this->getJson('/api/v1/notifications/unread-count')->assertUnauthorized();
        $this->getJson('/api/v1/notification-preferences')->assertUnauthorized();
    }
}
