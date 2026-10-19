<?php

namespace Tests\Feature\Modules\Recipients\Segments;

use App\Domain\Enums\RoleName;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Identity\Models\User;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Recipients\Models\RecipientList;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * docs/45-brevo-api-contracts.md §45.5.2 / §45.1 — invalid rule trees →
 * 422 with dot paths to the offending node (`rules.children.1.children.0.value`).
 */
class RuleTreeValidationTest extends TestCase
{
    use BuildsRuleTrees;
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->user = User::factory()->withRole(RoleName::Viewer)->create();
    }

    /**
     * @return array<string, array{array<string, mixed>, string, ?string}>
     */
    public static function invalidTrees(): array
    {
        $c = fn (string $f, string $o, mixed $v = null, array $x = []) => ['type' => 'condition', 'field' => $f, 'operator' => $o, 'value' => $v] + $x;
        $root = fn (array $children, string $op = 'and') => ['version' => 2, 'type' => 'group', 'operator' => $op, 'children' => $children];
        $eng = fn (array $v) => $v + ['campaign_ids' => null, 'within_days' => null, 'from' => null, 'to' => null, 'link_url' => null, 'min_count' => null, 'automation_step_id' => null];
        $ok = $c('attribute.EMAIL', 'contains', 'a');

        return [
            'unsupported version' => [['version' => 3] + $root([]), 'rules.version', null],
            'root not a group' => [['version' => 2, 'type' => 'condition', 'operator' => 'and', 'children' => []], 'rules.type', null],
            'bad root operator' => [$root([], 'xor'), 'rules.operator', null],
            'children not a list' => [['version' => 2, 'type' => 'group', 'operator' => 'and', 'children' => 'x'], 'rules.children', null],
            'unknown node type' => [$root([['type' => 'rule']]), 'rules.children.0.type', null],
            'unknown field' => [$root([$c('contact.shoe_size', 'equals', '42')]), 'rules.children.0.field', 'Champ inconnu : contact.shoe_size'],
            'invalid attribute name' => [$root([$c('attribute.a-b', 'equals', 'x')]), 'rules.children.0.field', 'Attribut inconnu : a-b'],
            'operator not allowed for type' => [$root([$ok, $c('contact.created_at', 'contains', 'x')]), 'rules.children.1.operator', null],
            'nested value error path' => [$root([$ok, ['type' => 'group', 'operator' => 'or', 'children' => [$c('attribute.AGE', 'equals', 'trente', ['field_type' => 'number'])]]]), 'rules.children.1.children.0.value', 'Saisissez un nombre.'],
            'group inside group' => [$root([['type' => 'group', 'operator' => 'and', 'children' => [['type' => 'group', 'operator' => 'and', 'children' => []]]]]), 'rules.children.0.children.0.type', null],
            'empty nested group' => [$root([['type' => 'group', 'operator' => 'and', 'children' => []]]), 'rules.children.0.children', null],
            'missing text value' => [$root([$c('attribute.EMAIL', 'equals', null)]), 'rules.children.0.value', null],
            'empty in list' => [$root([$c('attribute.EMAIL', 'in', [])]), 'rules.children.0.value', null],
            'between min > max' => [$root([$c('attribute.AGE', 'between', [10, 2], ['field_type' => 'number'])]), 'rules.children.0.value', null],
            'bad date' => [$root([$c('contact.created_at', 'on', '2026-02-30')]), 'rules.children.0.value', null],
            'days out of range' => [$root([$c('contact.created_at', 'in_last_days', 0)]), 'rules.children.0.value', null],
            'category option not allowed' => [$root([$c('contact.status', 'in', ['active', 'deleted'])]), 'rules.children.0.value.1', 'Valeur non autorisée : deleted'],
            'tags must be ids' => [$root([$c('contact.tags', 'contains_any', ['vip'])]), 'rules.children.0.value.0', null],
            'unknown list' => [$root([$c('list', 'in', ['00000000-0000-0000-0000-000000000000'])]), 'rules.children.0.value.0', null],
            'bad field_type' => [$root([$c('attribute.X', 'equals', 'a', ['field_type' => 'blob'])]), 'rules.children.0.field_type', null],
            'engagement value not an object' => [$root([$c('engagement.opened', 'has', 'yes')]), 'rules.children.0.value', null],
            'engagement within_days' => [$root([$c('engagement.opened', 'has', $eng(['within_days' => 0]))]), 'rules.children.0.value.within_days', null],
            'engagement unknown campaign' => [$root([$c('engagement.opened', 'has', $eng(['campaign_ids' => ['00000000-0000-0000-0000-000000000000']]))]), 'rules.children.0.value.campaign_ids.0', null],
            'engagement too many campaigns' => [$root([$c('engagement.opened', 'has', $eng(['campaign_ids' => array_fill(0, 51, 'x')]))]), 'rules.children.0.value.campaign_ids', null],
            'engagement min_count on sent' => [$root([$c('engagement.sent', 'has', $eng(['min_count' => 2]))]), 'rules.children.0.value.min_count', null],
            'engagement link_url on opened' => [$root([$c('engagement.opened', 'has', $eng(['link_url' => 'https://x.dz']))]), 'rules.children.0.value.link_url', null],
            'engagement from after to' => [$root([$c('engagement.opened', 'has', $eng(['from' => '2026-05-02', 'to' => '2026-05-01']))]), 'rules.children.0.value.to', null],
            'engagement operator' => [$root([$c('engagement.opened', 'equals', $eng([]))]), 'rules.children.0.operator', null],
        ];
    }

    /**
     * @param  array<string, mixed>  $tree
     */
    #[DataProvider('invalidTrees')]
    public function test_invalid_tree_returns_422_with_node_path(array $tree, string $path, ?string $message): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/segments/count', ['rules' => $tree]);

        $response->assertStatus(422)->assertJsonValidationErrors([$path]);
        if ($message !== null) {
            $this->assertSame($message, $response->json('errors')[$path][0]);
        }
    }

    public function test_more_than_100_conditions_is_rejected(): void
    {
        $conditions = array_fill(0, 101, $this->cond('attribute.EMAIL', 'contains', 'a'));

        $this->actingAs($this->user)->postJson('/api/v1/segments/count', ['rules' => $this->tree($conditions)])
            ->assertStatus(422)->assertJsonValidationErrors(['rules.children']);

        $this->actingAs($this->user)->postJson('/api/v1/segments/count', ['rules' => $this->tree(array_slice($conditions, 0, 100))])
            ->assertOk();
    }

    public function test_validation_on_segment_store_uses_rules_prefix(): void
    {
        $admin = User::factory()->withRole(RoleName::Administrator)->create();

        $this->actingAs($admin)->postJson('/api/v1/segments', [
            'name' => 'X',
            'rules' => $this->tree([$this->cond('list', 'in', 'not-an-array')]),
        ])->assertStatus(422)->assertJsonValidationErrors(['rules.children.0.value']);
    }

    public function test_validate_throws_validation_exception_with_custom_prefix(): void
    {
        try {
            $this->contract()->validate($this->tree([$this->cond('nope', 'equals', 'x')]), 'filters');
            $this->fail('ValidationException expected');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('filters.children.0.field', $e->errors());
        }
    }

    public function test_apply_validates_before_compiling(): void
    {
        $this->expectException(ValidationException::class);

        $this->contract()->apply(Recipient::query(), $this->tree([$this->cond('attribute.AGE', 'between', [1], ['field_type' => 'number'])]));
    }

    public function test_known_campaign_and_list_values_pass(): void
    {
        $campaign = Campaign::factory()->create();
        $list = RecipientList::query()->create(['name' => 'L', 'type' => 'static']);

        $this->actingAs($this->user)->postJson('/api/v1/segments/count', ['rules' => $this->tree([
            $this->cond('engagement.opened', 'has', $this->engagement(['campaign_ids' => [$campaign->uuid], 'within_days' => 30, 'min_count' => 1])),
            $this->cond('list', 'not_in', [$list->uuid]),
            $this->cond('attribute.'.Str::upper('ville'), 'in', ['alger'], ['field_type' => 'text']),
        ])])->assertOk()->assertJsonPath('data.count', 0);
    }
}
