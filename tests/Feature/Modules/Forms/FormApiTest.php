<?php

namespace Tests\Feature\Modules\Forms;

use App\Domain\Enums\RoleName;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Forms\Models\Form;
use App\Modules\Forms\Models\FormSubmission;
use App\Modules\Identity\Models\User;
use Database\Factories\RecipientListFactory;
use Database\Seeders\ContactAttributeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** docs/45 §45.7.4 — WP-90 forms API. */
class FormApiTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(ContactAttributeSeeder::class);
        $this->manager = User::factory()->withRole(RoleName::MarketingManager)->create();
    }

    /** @return array<string, mixed> */
    private function payload(array $over = []): array
    {
        $list = RecipientListFactory::new()->create();

        return array_merge([
            'name' => 'Newsletter',
            'list_ids' => [$list->uuid],
            'fields' => [
                ['attribute' => 'EMAIL', 'label' => 'Email', 'required' => true],
                ['attribute' => 'PRENOM', 'label' => 'Prénom'],
            ],
            'settings' => ['double_opt_in' => false],
        ], $over);
    }

    public function test_crud_flow_returns_contract_shape(): void
    {
        $r = $this->actingAs($this->manager)->postJson('/api/v1/forms', $this->payload())->assertCreated();
        $id = $r->json('data.id');
        $this->assertSame('draft', $r->json('data.status'));
        $this->assertSame('text', $r->json('data.fields.1.type'));
        $this->assertSame(url("/f/$id"), $r->json('data.share.hosted_url'));
        $this->assertStringContainsString("/f/$id/embed.js", $r->json('data.share.embed_html'));
        $this->assertSame(0, $r->json('data.submissions_count'));

        $this->actingAs($this->manager)->patchJson("/api/v1/forms/$id", ['name' => 'Autre', 'design' => ['button_text' => 'Go']])
            ->assertOk()->assertJsonPath('data.name', 'Autre')->assertJsonPath('data.design.button_text', 'Go')
            ->assertJsonPath('data.design.primary_color', '#0F6CBD');

        $this->actingAs($this->manager)->getJson("/api/v1/forms/$id")->assertOk();
        $this->actingAs($this->manager)->getJson('/api/v1/forms?q=Autre&status=draft')->assertOk()->assertJsonCount(1, 'data');

        $copy = $this->actingAs($this->manager)->postJson("/api/v1/forms/$id/duplicate")->assertCreated();
        $this->assertSame('draft', $copy->json('data.status'));

        $this->actingAs($this->manager)->deleteJson("/api/v1/forms/$id")->assertNoContent();
        $this->getJson("/f/$id")->assertNotFound();
    }

    public function test_validation_rejects_unknown_attribute(): void
    {
        $this->actingAs($this->manager)->postJson('/api/v1/forms', $this->payload(['fields' => [['attribute' => 'NOPE']]]))
            ->assertUnprocessable()->assertJsonValidationErrors(['fields.0.attribute']);
    }

    public function test_permissions(): void
    {
        $viewer = User::factory()->withRole(RoleName::Viewer)->create();
        $operator = User::factory()->withRole(RoleName::MarketingOperator)->create();

        $this->postJson('/api/v1/forms', $this->payload())->assertUnauthorized();
        $this->actingAs($viewer)->postJson('/api/v1/forms', $this->payload())->assertForbidden();
        $this->actingAs($operator)->postJson('/api/v1/forms', $this->payload())->assertForbidden();

        $form = Form::factory()->create();
        $this->actingAs($viewer)->getJson('/api/v1/forms')->assertOk();
        $this->actingAs($viewer)->getJson("/api/v1/forms/{$form->uuid}")->assertOk();
        $this->actingAs($viewer)->deleteJson("/api/v1/forms/{$form->uuid}")->assertForbidden();
        $this->actingAs($viewer)->postJson("/api/v1/forms/{$form->uuid}/publish")->assertForbidden();
        $this->actingAs($viewer)->getJson("/api/v1/forms/{$form->uuid}/submissions")->assertForbidden();
    }

    public function test_publish_requires_list_and_email_field_and_is_audited(): void
    {
        $form = Form::factory()->draft()->create(['list_ids' => []]);
        $this->actingAs($this->manager)->postJson("/api/v1/forms/{$form->uuid}/publish")
            ->assertUnprocessable()->assertJsonValidationErrors(['list_ids']);

        $list = RecipientListFactory::new()->create();
        $form->update(['list_ids' => [$list->uuid], 'fields' => [['attribute' => 'PRENOM', 'label' => 'P', 'required' => false]]]);
        $this->actingAs($this->manager)->postJson("/api/v1/forms/{$form->uuid}/publish")
            ->assertUnprocessable()->assertJsonValidationErrors(['fields']);

        $form->update(['fields' => [['attribute' => 'EMAIL', 'label' => 'E', 'required' => true]]]);
        $this->actingAs($this->manager)->postJson("/api/v1/forms/{$form->uuid}/publish")->assertOk()->assertJsonPath('data.status', 'active');
        $this->assertTrue(AuditLog::query()->where('action', 'form.published')->exists());

        $this->actingAs($this->manager)->postJson("/api/v1/forms/{$form->uuid}/unpublish")->assertOk()->assertJsonPath('data.status', 'inactive');
        $this->getJson("/f/{$form->uuid}")->assertNotFound();
    }

    public function test_submissions_are_cursor_paginated_and_filterable(): void
    {
        $form = Form::factory()->create();
        FormSubmission::factory()->count(3)->create(['form_id' => $form->id]);
        FormSubmission::factory()->create(['form_id' => $form->id, 'status' => 'pending_confirmation']);

        $this->actingAs($this->manager)->getJson("/api/v1/forms/{$form->uuid}/submissions?per_page=2")
            ->assertOk()->assertJsonCount(2, 'data')->assertJsonStructure(['data', 'links', 'meta']);
        $this->actingAs($this->manager)->getJson("/api/v1/forms/{$form->uuid}/submissions?status=pending_confirmation")
            ->assertOk()->assertJsonCount(1, 'data');
    }
}
