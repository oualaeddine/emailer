<?php

namespace Tests\Feature\Modules\Tracking;

use App\Domain\Enums\MessageStatus;
use App\Domain\Enums\RoleName;
use App\Modules\Composer\Models\Draft;
use App\Modules\Identity\Models\User;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Tracking\Models\Message;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * docs/16-email-history.md §16.1, docs/20-delivery-tracking.md,
 * docs/29-api-specification.md — /api/v1/messages.
 */
class MessageTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_a_marketing_operator_only_sees_their_own_messages_in_the_list(): void
    {
        $owner = User::factory()->withRole(RoleName::MarketingOperator)->create();
        $otherOperator = User::factory()->withRole(RoleName::MarketingOperator)->create();

        $this->createMessageForUser($owner, 'mine@example.com');
        $this->createMessageForUser($otherOperator, 'not-mine@example.com');

        $response = $this->actingAs($owner)->getJson('/api/v1/messages');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.recipient_email', 'mine@example.com');
    }

    public function test_a_marketing_manager_with_mailbox_view_all_sees_every_message(): void
    {
        $manager = User::factory()->withRole(RoleName::MarketingManager)->create();
        $operatorA = User::factory()->withRole(RoleName::MarketingOperator)->create();
        $operatorB = User::factory()->withRole(RoleName::MarketingOperator)->create();

        $this->createMessageForUser($operatorA, 'a@example.com');
        $this->createMessageForUser($operatorB, 'b@example.com');

        $response = $this->actingAs($manager)->getJson('/api/v1/messages');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    public function test_a_viewer_without_any_mailbox_permission_cannot_list_messages(): void
    {
        $viewer = User::factory()->withRole(RoleName::Viewer)->create();

        $this->actingAs($viewer)->getJson('/api/v1/messages')->assertForbidden();
    }

    public function test_a_marketing_operator_cannot_fetch_another_operators_message_by_uuid(): void
    {
        $owner = User::factory()->withRole(RoleName::MarketingOperator)->create();
        $otherOperator = User::factory()->withRole(RoleName::MarketingOperator)->create();

        $message = $this->createMessageForUser($owner, 'private@example.com');

        $this->actingAs($otherOperator)
            ->getJson("/api/v1/messages/{$message->uuid}")
            ->assertForbidden();
    }

    public function test_a_marketing_operator_can_fetch_their_own_message_by_uuid(): void
    {
        $owner = User::factory()->withRole(RoleName::MarketingOperator)->create();
        $message = $this->createMessageForUser($owner, 'mine@example.com', MessageStatus::Delivered);

        $response = $this->actingAs($owner)->getJson("/api/v1/messages/{$message->uuid}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $message->uuid);
        $response->assertJsonPath('data.status', 'delivered');
    }

    public function test_show_returns_404_for_an_unknown_uuid(): void
    {
        $manager = User::factory()->withRole(RoleName::MarketingManager)->create();

        $this->actingAs($manager)
            ->getJson('/api/v1/messages/'.\Illuminate\Support\Str::uuid()->toString())
            ->assertNotFound();
    }

    public function test_the_list_can_be_filtered_by_folder_and_is_paginated(): void
    {
        $manager = User::factory()->withRole(RoleName::MarketingManager)->create();

        $this->createMessageForUser($manager, 'sent1@example.com', MessageStatus::Delivered);
        $this->createMessageForUser($manager, 'sent2@example.com', MessageStatus::Accepted);
        $this->createMessageForUser($manager, 'outbox@example.com', MessageStatus::Queued);
        $this->createMessageForUser($manager, 'failed@example.com', MessageStatus::HardBounced);

        $sentResponse = $this->actingAs($manager)->getJson('/api/v1/messages?folder=sent');
        $sentResponse->assertOk();
        $sentResponse->assertJsonCount(2, 'data');

        $outboxResponse = $this->actingAs($manager)->getJson('/api/v1/messages?folder=outbox');
        $outboxResponse->assertOk();
        $outboxResponse->assertJsonCount(1, 'data');
        $outboxResponse->assertJsonPath('data.0.recipient_email', 'outbox@example.com');

        $failedResponse = $this->actingAs($manager)->getJson('/api/v1/messages?folder=failed');
        $failedResponse->assertOk();
        $failedResponse->assertJsonCount(1, 'data');
        $failedResponse->assertJsonPath('data.0.recipient_email', 'failed@example.com');

        $paginated = $this->actingAs($manager)->getJson('/api/v1/messages?page=1');
        $paginated->assertOk();
        $paginated->assertJsonPath('meta.total', 4);
    }

    public function test_the_list_can_be_filtered_by_recipient_search(): void
    {
        $manager = User::factory()->withRole(RoleName::MarketingManager)->create();

        $this->createMessageForUser($manager, 'karim@example.com');
        $this->createMessageForUser($manager, 'amine@example.com');

        $response = $this->actingAs($manager)->getJson('/api/v1/messages?q=karim');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.recipient_email', 'karim@example.com');
    }

    private function createMessageForUser(
        User $user,
        string $recipientEmail,
        MessageStatus $status = MessageStatus::Queued,
    ): Message {
        $draft = Draft::query()->create([
            'user_id' => $user->id,
            'subject' => 'Sujet',
            'html_body' => '<p>Contenu</p>',
            'status' => 'draft',
        ]);

        $recipient = Recipient::factory()->create(['email' => $recipientEmail]);

        return Message::factory()->create([
            'draft_id' => $draft->id,
            'recipient_id' => $recipient->id,
            'status' => $status->value,
        ]);
    }
}
