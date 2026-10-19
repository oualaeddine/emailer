<?php

namespace Tests\Feature\Modules\Recipients\Segments;

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Recipients\Models\RecipientList;
use App\Modules\Recipients\Models\Tag;
use App\Modules\Recipients\Segments\Services\FieldCatalog;
use App\Modules\Tracking\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Property-based style checks of the §45.5.2 compiler on a random contact
 * base (fixed seeds, reproducible):
 *  - every random tree compiles and its result equals the set algebra
 *    (AND = intersection, OR = union) of its conditions' results;
 *  - count() == compile()->count() == |ids|, and matches() agrees;
 *  - every negative operator is the exact complement of its positive one.
 */
class RuleTreePropertyTest extends TestCase
{
    use BuildsRuleTrees;
    use RefreshDatabase;

    private const SEED = 4242;

    private const CONTACTS = 40;

    /** @var list<array<string, mixed>> */
    private array $pool = [];

    /** @var list<int> */
    private array $all = [];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-03-01 08:00:00', 'UTC'));
        mt_srand(self::SEED);
        $this->seedContacts();
        $this->pool = $this->conditionPool();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function pick(array $values): mixed
    {
        return $values[mt_rand(0, count($values) - 1)];
    }

    private function seedContacts(): void
    {
        $tags = collect(['vip', 'b2b', 'new'])->map(fn ($n) => Tag::query()->create(['name' => $n, 'color' => 'brand']));
        $lists = collect(['L1', 'L2'])->map(fn ($n) => RecipientList::query()->create(['name' => $n, 'type' => 'static']));
        $campaign = Campaign::factory()->create();

        for ($i = 0; $i < self::CONTACTS; $i++) {
            $custom = array_filter([
                'VILLE' => $this->pick(['Alger', 'oran', 'Constantine', '', null]),
                'AGE' => $this->pick([18, 25, 40, 61, null, '33']),
                'NAISSANCE' => $this->pick(['1980-01-15', '1995-07-30', '2001-03-01', null]),
                'CLIENT' => $this->pick([true, false, null]),
                'INTERETS' => $this->pick([['sport'], ['tech', 'sport'], [], null]),
            ], fn ($v) => $v !== null);

            $recipient = Recipient::factory()->create([
                'company_name' => 'C'.$i,
                'status' => $this->pick(['active', 'active', 'invalid', 'suppressed']),
                'source' => $this->pick(['manual', 'csv_import', 'pagejaunes']),
                'custom_fields' => $custom,
            ]);
            $recipient->forceFill(['created_at' => now()->subDays(mt_rand(0, 400))])->saveQuietly();

            foreach ($tags as $tag) {
                if (mt_rand(0, 2) === 0) {
                    $recipient->tags()->attach($tag);
                }
            }
            foreach ($lists as $list) {
                if (mt_rand(0, 2) === 0) {
                    $list->recipients()->attach($recipient->id, ['added_at' => now()]);
                }
            }
            if (mt_rand(0, 1) === 0) {
                $message = Message::factory()->create(['recipient_id' => $recipient->id, 'campaign_id' => $campaign->id]);
                $opened = mt_rand(0, 1) === 0;
                $message->forceFill([
                    'status' => $opened ? 'opened' : 'delivered',
                    'sent_at' => now()->subDays(mt_rand(1, 90)),
                    'delivered_at' => now()->subDays(1),
                    'opened_at' => $opened ? now()->subDays(mt_rand(0, 60)) : null,
                    'open_count' => $opened ? mt_rand(1, 4) : 0,
                ])->save();
            }
            if ($i === 0) {
                $recipient->delete(); // soft-deleted: never matches
            }
        }

        $this->all = Recipient::query()->orderBy('id')->pluck('id')->all();
        $this->tagIds = $tags->pluck('id')->all();
        $this->listIds = $lists->pluck('uuid')->all();
        $this->campaignUuid = $campaign->uuid;
    }

    /** @var list<int> */
    private array $tagIds = [];

    /** @var list<string> */
    private array $listIds = [];

    private string $campaignUuid = '';

    /**
     * @return list<array<string, mixed>>
     */
    private function conditionPool(): array
    {
        $t = ['field_type' => 'text'];
        $n = ['field_type' => 'number'];
        $d = ['field_type' => 'date'];
        $b = ['field_type' => 'boolean'];
        $m = ['field_type' => 'multiple_choice'];

        return [
            $this->cond('attribute.VILLE', 'equals', 'alger', $t),
            $this->cond('attribute.VILLE', 'not_equals', 'ALGER', $t),
            $this->cond('attribute.VILLE', 'contains', 'an', $t),
            $this->cond('attribute.VILLE', 'not_contains', 'an', $t),
            $this->cond('attribute.VILLE', 'starts_with', 'o', $t),
            $this->cond('attribute.VILLE', 'ends_with', 'e', $t),
            $this->cond('attribute.VILLE', 'in', ['oran', 'constantine'], $t),
            $this->cond('attribute.VILLE', 'not_in', ['oran', 'constantine'], $t),
            $this->cond('attribute.VILLE', 'is_empty', null, $t),
            $this->cond('attribute.VILLE', 'is_not_empty', null, $t),
            $this->cond('attribute.AGE', 'equals', 25, $n),
            $this->cond('attribute.AGE', 'not_equals', 25, $n),
            $this->cond('attribute.AGE', 'greater_than', 30, $n),
            $this->cond('attribute.AGE', 'greater_or_equal', 40, $n),
            $this->cond('attribute.AGE', 'less_than', 33, $n),
            $this->cond('attribute.AGE', 'less_or_equal', 33, $n),
            $this->cond('attribute.AGE', 'between', [20, 45], $n),
            $this->cond('attribute.AGE', 'is_empty', null, $n),
            $this->cond('attribute.AGE', 'is_not_empty', null, $n),
            $this->cond('attribute.NAISSANCE', 'before', '1996-01-01', $d),
            $this->cond('attribute.NAISSANCE', 'after', '1990-01-01', $d),
            $this->cond('attribute.NAISSANCE', 'between', ['1990-01-01', '2002-01-01'], $d),
            $this->cond('attribute.NAISSANCE', 'on', '2001-03-01', $d),
            $this->cond('attribute.NAISSANCE', 'anniversary_today', null, $d),
            $this->cond('attribute.NAISSANCE', 'is_empty', null, $d),
            $this->cond('attribute.CLIENT', 'is_true', null, $b),
            $this->cond('attribute.CLIENT', 'is_false', null, $b),
            $this->cond('attribute.CLIENT', 'is_empty', null, $b),
            $this->cond('attribute.INTERETS', 'contains_any', ['tech'], $m),
            $this->cond('attribute.INTERETS', 'contains_all', ['tech', 'sport'], $m),
            $this->cond('attribute.INTERETS', 'not_contains_any', ['sport'], $m),
            $this->cond('attribute.INTERETS', 'is_empty', null, $m),
            $this->cond('contact.status', 'in', ['active']),
            $this->cond('contact.status', 'not_in', ['active', 'invalid']),
            $this->cond('contact.source', 'in', ['pagejaunes', 'manual']),
            $this->cond('contact.created_at', 'in_last_days', 100),
            $this->cond('contact.created_at', 'more_than_days_ago', 200),
            $this->cond('contact.created_at', 'before', '2025-06-01'),
            $this->cond('contact.tags', 'contains_any', [$this->tagIds[0]]),
            $this->cond('contact.tags', 'contains_all', [$this->tagIds[0], $this->tagIds[1]]),
            $this->cond('contact.tags', 'not_contains_any', [$this->tagIds[2]]),
            $this->cond('contact.tags', 'is_empty'),
            $this->cond('list', 'in', [$this->listIds[0]]),
            $this->cond('list', 'not_in', $this->listIds),
            $this->cond('engagement.opened', 'has', $this->engagement(['within_days' => 30])),
            $this->cond('engagement.opened', 'has_not', $this->engagement()),
            $this->cond('engagement.opened', 'has', $this->engagement(['min_count' => 2, 'campaign_ids' => [$this->campaignUuid]])),
            $this->cond('engagement.delivered', 'has', $this->engagement()),
            $this->cond('engagement.sent', 'has', $this->engagement(['within_days' => 45])),
        ];
    }

    /**
     * @param  array<string, mixed>  $tree
     * @return list<int>
     */
    private function ids(array $tree): array
    {
        $ids = $this->compiler()->compile($tree)->pluck('recipients.id')->map(fn ($id) => (int) $id)->all();
        sort($ids);

        return $ids;
    }

    /**
     * @param  array<int, list<int>>  $sets
     * @return list<int>
     */
    private function combine(string $operator, array $sets): array
    {
        if ($sets === []) {
            return $this->all;
        }
        $result = array_shift($sets);
        foreach ($sets as $set) {
            $result = $operator === 'and' ? array_intersect($result, $set) : array_merge($result, $set);
        }
        $result = array_values(array_unique($result));
        sort($result);

        return $result;
    }

    public function test_random_trees_match_set_algebra_of_their_conditions(): void
    {
        $single = [];
        foreach ($this->pool as $i => $condition) {
            $single[$i] = $this->ids($this->tree([$condition]));
        }

        for ($iteration = 0; $iteration < 60; $iteration++) {
            $rootOperator = $this->pick(['and', 'or']);
            $children = [];
            $sets = [];

            for ($c = mt_rand(1, 4); $c > 0; $c--) {
                if (mt_rand(0, 2) === 0) {
                    $groupOperator = $this->pick(['and', 'or']);
                    $groupChildren = [];
                    $groupSets = [];
                    for ($g = mt_rand(1, 3); $g > 0; $g--) {
                        $index = mt_rand(0, count($this->pool) - 1);
                        $groupChildren[] = $this->pool[$index];
                        $groupSets[] = $single[$index];
                    }
                    $children[] = $this->group($groupChildren, $groupOperator);
                    $sets[] = $this->combine($groupOperator, $groupSets);
                } else {
                    $index = mt_rand(0, count($this->pool) - 1);
                    $children[] = $this->pool[$index];
                    $sets[] = $single[$index];
                }
            }

            $tree = $this->tree($children, $rootOperator);
            $expected = $this->combine($rootOperator, $sets);
            $message = 'iteration '.$iteration.': '.json_encode($tree);

            $this->assertSame($expected, $this->ids($tree), $message);
            $this->assertSame(count($expected), $this->contract()->count($tree), $message);
            $this->assertSame(count($expected), $this->compiler()->compile($tree)->count(), $message);

            $probe = Recipient::query()->find($this->pick($this->all));
            $this->assertSame(in_array($probe->id, $expected, true), $this->contract()->matches($probe, $tree), $message);
        }
    }

    public function test_negative_operators_are_exact_complements(): void
    {
        $negations = [
            'not_equals' => 'equals', 'not_contains' => 'contains', 'not_in' => 'in',
            'is_empty' => 'is_not_empty', 'not_contains_any' => 'contains_any', 'has_not' => 'has',
        ];
        $total = count($this->all);
        $checked = 0;

        foreach ($this->pool as $condition) {
            $operator = $condition['operator'];
            $positive = $negations[$operator] ?? array_search($operator, $negations, true);
            $type = app(FieldCatalog::class)->definition($condition['field'], $condition['field_type'] ?? null)['type'];
            if ($positive === false || ! in_array($positive, FieldCatalog::OPERATORS[$type], true)) {
                continue;
            }
            $complement = ['operator' => $positive] + $condition;

            $a = $this->ids($this->tree([$condition]));
            $b = $this->ids($this->tree([$complement]));

            $this->assertSame([], array_values(array_intersect($a, $b)), json_encode($condition));
            $this->assertSame($total, count($a) + count($b), json_encode($condition));
            $checked++;
        }

        $this->assertGreaterThan(15, $checked);
    }

    public function test_and_is_bounded_by_children_and_or_by_union(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $x = $this->pick($this->pool);
            $y = $this->pick($this->pool);
            $cx = $this->compiler()->count($this->tree([$x]));
            $cy = $this->compiler()->count($this->tree([$y]));
            $and = $this->compiler()->count($this->tree([$x, $y]));
            $or = $this->compiler()->count($this->tree([$x, $y], 'or'));

            $this->assertLessThanOrEqual(min($cx, $cy), $and);
            $this->assertGreaterThanOrEqual(max($cx, $cy), $or);
            $this->assertSame($cx + $cy, $and + $or); // inclusion–exclusion
        }
    }
}
