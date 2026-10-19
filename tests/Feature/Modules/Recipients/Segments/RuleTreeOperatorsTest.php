<?php

namespace Tests\Feature\Modules\Recipients\Segments;

use App\Domain\Enums\RecipientSource;
use App\Domain\Enums\RecipientStatus;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Recipients\Models\RecipientList;
use App\Modules\Recipients\Models\Tag;
use App\Modules\Suppression\Models\SuppressionEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * docs/45-brevo-api-contracts.md §45.5.2 — every operator × field type.
 *
 * Fixture (company_name = label; "now" = 2026-05-12 10:00 UTC = 11:00 in
 * Africa/Algiers):
 *  A alice@acme.dz  VILLE Alger, AGE 30, NAISSANCE 1990-05-12, CLIENT true, INTERETS [sport, tech], OWNER u-1, NIVEAU gold
 *  B bob@beta.dz    VILLE oran,  AGE 45, NAISSANCE 1985-11-02, CLIENT false, INTERETS [music], OWNER u-2 — blocklisted
 *  C carol@gamma.com VILLE '',  AGE "12", INTERETS [] — status suppressed
 *  D dan@delta.com  no custom fields, no first name
 *  (E soft-deleted, never matches)
 */
class RuleTreeOperatorsTest extends TestCase
{
    use BuildsRuleTrees;
    use RefreshDatabase;

    /** @var array<string, int> */
    private array $tags = [];

    /** @var array<string, string> */
    private array $lists = [];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-05-12 10:00:00', 'UTC'));
        $this->seedFixture();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function seedFixture(): void
    {
        $pj1 = DB::table('pagejaunes_company_cache')->insertGetId([
            'pagejaunes_company_id' => 1, 'pagejaunes_code' => 'PJ1', 'wilaya_label' => 'Alger',
            'legal_form_label' => 'SARL', 'is_exporter' => true, 'raw_source_data' => json_encode(['sector' => 'Agroalimentaire']),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $pj2 = DB::table('pagejaunes_company_cache')->insertGetId([
            'pagejaunes_company_id' => 2, 'pagejaunes_code' => 'PJ2', 'wilaya_label' => 'Oran',
            'legal_form_label' => 'EURL', 'is_exporter' => false, 'raw_source_data' => json_encode(['sector' => 'BTP']),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $today = '2026-05-12';
        $a = $this->recipient('A', 'alice@acme.dz', 'Alice', [
            'VILLE' => 'Alger', 'AGE' => 30, 'NAISSANCE' => '1990-05-12', 'CLIENT' => true,
            'INTERETS' => ['sport', 'tech'], 'OWNER' => 'u-1', 'NIVEAU' => 'gold', 'RDV' => '2026-05-09',
        ], '2026-05-01 10:00:00', ['pagejaunes_company_cache_id' => $pj1, 'source' => RecipientSource::PageJaunes->value, 'last_contacted_at' => '2026-05-10 08:00:00']);
        $b = $this->recipient('B', 'bob@beta.dz', 'Bob', [
            'VILLE' => 'oran', 'AGE' => 45, 'NAISSANCE' => '1985-11-02', 'CLIENT' => false,
            'INTERETS' => ['music'], 'OWNER' => 'u-2', 'RDV' => '2026-05-17',
        ], '2026-05-11 23:30:00', ['pagejaunes_company_cache_id' => $pj2, 'status' => RecipientStatus::Invalid->value]);
        $c = $this->recipient('C', 'carol@gamma.com', 'Carol', [
            'VILLE' => '', 'AGE' => '12', 'INTERETS' => [], 'RDV' => '2026-04-02',
        ], '2026-04-01 12:00:00', ['status' => RecipientStatus::Suppressed->value]);
        $d = $this->recipient('D', 'dan@delta.com', null, [], $today.' 09:00:00');
        $e = $this->recipient('E', 'eve@deleted.dz', 'Eve', ['VILLE' => 'Alger', 'AGE' => 30], $today.' 09:00:00');
        $e->delete();

        SuppressionEntry::query()->create(['email' => 'bob@beta.dz', 'reason' => 'manual_block']);

        $vip = Tag::query()->create(['name' => 'vip', 'color' => 'brand']);
        $prospect = Tag::query()->create(['name' => 'prospect', 'color' => 'brand']);
        $a->tags()->attach($vip);
        $b->tags()->attach([$vip->id, $prospect->id]);
        $e->tags()->attach($vip);
        $this->tags = ['vip' => $vip->id, 'prospect' => $prospect->id];

        $l1 = RecipientList::query()->create(['name' => 'Clients', 'type' => 'static']);
        $l2 = RecipientList::query()->create(['name' => 'Prospects', 'type' => 'static']);
        $l1->recipients()->attach([$a->id => ['added_at' => now()], $c->id => ['added_at' => now()]]);
        $l2->recipients()->attach([$b->id => ['added_at' => now()]]);
        $this->lists = ['l1' => $l1->uuid, 'l2' => $l2->uuid];
    }

    /**
     * @param  array<string, mixed>  $custom
     * @param  array<string, mixed>  $extra
     */
    private function recipient(string $label, string $email, ?string $first, array $custom, string $createdAt, array $extra = []): Recipient
    {
        $recipient = Recipient::factory()->create(array_merge([
            'company_name' => $label, 'email' => $email, 'first_name' => $first, 'custom_fields' => $custom,
        ], $extra));
        $recipient->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->saveQuietly();

        return $recipient;
    }

    /**
     * @return array<string, array{string, string, mixed, list<string>, array<string, mixed>}>
     */
    public static function operatorCases(): array
    {
        $text = ['field_type' => 'text'];
        $number = ['field_type' => 'number'];
        $date = ['field_type' => 'date'];
        $bool = ['field_type' => 'boolean'];
        $category = ['field_type' => 'category'];
        $multi = ['field_type' => 'multiple_choice'];
        $user = ['field_type' => 'user'];

        return [
            // text (JSON attribute) — case-insensitive
            'text equals' => ['attribute.VILLE', 'equals', 'ALGER', ['A'], $text],
            'text not_equals' => ['attribute.VILLE', 'not_equals', 'alger', ['B', 'C', 'D'], $text],
            'text contains' => ['attribute.VILLE', 'contains', 'RA', ['B'], $text],
            'text not_contains' => ['attribute.VILLE', 'not_contains', 'ra', ['A', 'C', 'D'], $text],
            'text starts_with' => ['attribute.VILLE', 'starts_with', 'al', ['A'], $text],
            'text ends_with' => ['attribute.VILLE', 'ends_with', 'AN', ['B'], $text],
            'text in' => ['attribute.VILLE', 'in', ['alger', 'ORAN'], ['A', 'B'], $text],
            'text not_in' => ['attribute.VILLE', 'not_in', ['alger', 'oran'], ['C', 'D'], $text],
            'text is_empty' => ['attribute.VILLE', 'is_empty', null, ['C', 'D'], $text],
            'text is_not_empty' => ['attribute.VILLE', 'is_not_empty', null, ['A', 'B'], $text],
            'text default type' => ['attribute.VILLE', 'equals', 'oran', ['B'], []],
            // text (built-in attribute columns)
            'email ends_with' => ['attribute.EMAIL', 'ends_with', '.DZ', ['A', 'B'], []],
            'email contains escapes wildcard' => ['attribute.EMAIL', 'contains', '%', [], []],
            'email contains underscore literal' => ['attribute.EMAIL', 'contains', '_', [], []],
            'prenom is_empty' => ['attribute.PRENOM', 'is_empty', null, ['D'], []],
            // number
            'number equals' => ['attribute.AGE', 'equals', 30, ['A'], $number],
            'number not_equals' => ['attribute.AGE', 'not_equals', 30, ['B', 'C', 'D'], $number],
            'number greater_than' => ['attribute.AGE', 'greater_than', 30, ['B'], $number],
            'number greater_or_equal' => ['attribute.AGE', 'greater_or_equal', 30, ['A', 'B'], $number],
            'number less_than (string stored)' => ['attribute.AGE', 'less_than', 30, ['C'], $number],
            'number less_or_equal' => ['attribute.AGE', 'less_or_equal', 12, ['C'], $number],
            'number between' => ['attribute.AGE', 'between', [12, 30], ['A', 'C'], $number],
            'number decimal' => ['attribute.AGE', 'greater_than', 29.5, ['A', 'B'], $number],
            'number is_empty' => ['attribute.AGE', 'is_empty', null, ['D'], $number],
            'number is_not_empty' => ['attribute.AGE', 'is_not_empty', null, ['A', 'B', 'C'], $number],
            // date (date-only attribute)
            'date on' => ['attribute.NAISSANCE', 'on', '1990-05-12', ['A'], $date],
            'date before' => ['attribute.NAISSANCE', 'before', '1990-01-01', ['B'], $date],
            'date after' => ['attribute.NAISSANCE', 'after', '1986-01-01', ['A'], $date],
            'date between' => ['attribute.NAISSANCE', 'between', ['1985-01-01', '1989-12-31'], ['B'], $date],
            'date anniversary_today' => ['attribute.NAISSANCE', 'anniversary_today', null, ['A'], $date],
            'date is_empty' => ['attribute.NAISSANCE', 'is_empty', null, ['C', 'D'], $date],
            'date is_not_empty' => ['attribute.NAISSANCE', 'is_not_empty', null, ['A', 'B'], $date],
            'date in_last_days' => ['attribute.RDV', 'in_last_days', 7, ['A'], $date],
            'date more_than_days_ago' => ['attribute.RDV', 'more_than_days_ago', 30, ['C'], $date],
            'date in_next_days' => ['attribute.RDV', 'in_next_days', 7, ['B'], $date],
            // date (timestamp column, account timezone Africa/Algiers = UTC+1)
            'created_at on (tz)' => ['contact.created_at', 'on', '2026-05-12', ['B', 'D'], []],
            'created_at before' => ['contact.created_at', 'before', '2026-05-01', ['C'], []],
            'created_at after (tz)' => ['contact.created_at', 'after', '2026-05-11', ['B', 'D'], []],
            'created_at between' => ['contact.created_at', 'between', ['2026-04-01', '2026-05-01'], ['A', 'C'], []],
            'created_at in_last_days' => ['contact.created_at', 'in_last_days', 15, ['A', 'B', 'D'], []],
            'created_at more_than_days_ago' => ['contact.created_at', 'more_than_days_ago', 30, ['C'], []],
            'created_at in_next_days' => ['contact.created_at', 'in_next_days', 3, ['B', 'D'], []],
            'created_at anniversary_today' => ['contact.created_at', 'anniversary_today', null, ['B', 'D'], []],
            'last_contacted_at is_not_empty' => ['contact.last_contacted_at', 'is_not_empty', null, ['A'], []],
            'last_contacted_at is_empty' => ['contact.last_contacted_at', 'is_empty', null, ['B', 'C', 'D'], []],
            'blocklisted_at is_not_empty' => ['contact.blocklisted_at', 'is_not_empty', null, ['B'], []],
            'last_opened_at is_empty' => ['contact.last_opened_at', 'is_empty', null, ['A', 'B', 'C', 'D'], []],
            // boolean
            'bool is_true' => ['attribute.CLIENT', 'is_true', null, ['A'], $bool],
            'bool is_false' => ['attribute.CLIENT', 'is_false', null, ['B'], $bool],
            'bool is_empty' => ['attribute.CLIENT', 'is_empty', null, ['C', 'D'], $bool],
            'blocklisted is_true' => ['contact.blocklisted', 'is_true', null, ['B'], []],
            'blocklisted is_false' => ['contact.blocklisted', 'is_false', null, ['A', 'C', 'D'], []],
            'blocklisted is_empty' => ['contact.blocklisted', 'is_empty', null, [], []],
            // category
            'status in' => ['contact.status', 'in', ['active'], ['A', 'D'], []],
            'status not_in' => ['contact.status', 'not_in', ['active'], ['B', 'C'], []],
            'status is_not_empty' => ['contact.status', 'is_not_empty', null, ['A', 'B', 'C', 'D'], []],
            'status is_empty' => ['contact.status', 'is_empty', null, [], []],
            'source in' => ['contact.source', 'in', ['pagejaunes'], ['A'], []],
            'category attribute in' => ['attribute.NIVEAU', 'in', ['GOLD'], ['A'], $category],
            'category attribute is_empty' => ['attribute.NIVEAU', 'is_empty', null, ['B', 'C', 'D'], $category],
            // multiple_choice
            'multi contains_any' => ['attribute.INTERETS', 'contains_any', ['tech', 'music'], ['A', 'B'], $multi],
            'multi contains_all' => ['attribute.INTERETS', 'contains_all', ['sport', 'tech'], ['A'], $multi],
            'multi not_contains_any' => ['attribute.INTERETS', 'not_contains_any', ['sport'], ['B', 'C', 'D'], $multi],
            'multi is_empty' => ['attribute.INTERETS', 'is_empty', null, ['C', 'D'], $multi],
            'multi is_not_empty' => ['attribute.INTERETS', 'is_not_empty', null, ['A', 'B'], $multi],
            // user
            'user in' => ['attribute.OWNER', 'in', ['u-1'], ['A'], $user],
            'user not_in' => ['attribute.OWNER', 'not_in', ['u-1'], ['B', 'C', 'D'], $user],
            'user is_empty' => ['attribute.OWNER', 'is_empty', null, ['C', 'D'], $user],
            // PagesJaunes
            'pj wilaya equals' => ['pagejaunes.wilaya', 'equals', 'alger', ['A'], []],
            'pj wilaya not_equals' => ['pagejaunes.wilaya', 'not_equals', 'alger', ['B', 'C', 'D'], []],
            'pj wilaya is_empty' => ['pagejaunes.wilaya', 'is_empty', null, ['C', 'D'], []],
            'pj wilaya is_not_empty' => ['pagejaunes.wilaya', 'is_not_empty', null, ['A', 'B'], []],
            'pj legal_form in' => ['pagejaunes.legal_form', 'in', ['eurl'], ['B'], []],
            'pj sector contains' => ['pagejaunes.sector', 'contains', 'agro', ['A'], []],
            'pj locality is_empty' => ['pagejaunes.locality', 'is_empty', null, ['A', 'B', 'C', 'D'], []],
            'pj exporter is_true' => ['pagejaunes.is_exporter', 'is_true', null, ['A'], []],
            'pj exporter is_false' => ['pagejaunes.is_exporter', 'is_false', null, ['B'], []],
            'pj exporter is_empty' => ['pagejaunes.is_exporter', 'is_empty', null, ['C', 'D'], []],
        ];
    }

    /**
     * @param  list<string>  $expected
     * @param  array<string, mixed>  $extra
     */
    #[DataProvider('operatorCases')]
    public function test_operator(string $field, string $operator, mixed $value, array $expected, array $extra): void
    {
        $condition = $this->cond($field, $operator, $value, $extra);

        $this->assertSame($expected, $this->labelsFor($condition));
        $this->assertSame(count($expected), $this->compiler()->count($this->tree([$condition])));
    }

    public function test_tags_operators(): void
    {
        ['vip' => $vip, 'prospect' => $prospect] = $this->tags;

        $this->assertSame(['A', 'B'], $this->labelsFor($this->cond('contact.tags', 'contains_any', [$vip])));
        $this->assertSame(['B'], $this->labelsFor($this->cond('contact.tags', 'contains_all', [$vip, $prospect])));
        $this->assertSame(['A', 'C', 'D'], $this->labelsFor($this->cond('contact.tags', 'not_contains_any', [$prospect])));
        $this->assertSame(['C', 'D'], $this->labelsFor($this->cond('contact.tags', 'is_empty')));
        $this->assertSame(['A', 'B'], $this->labelsFor($this->cond('contact.tags', 'is_not_empty')));
    }

    public function test_list_in_and_not_in(): void
    {
        ['l1' => $l1, 'l2' => $l2] = $this->lists;

        $this->assertSame(['A', 'C'], $this->labelsFor($this->cond('list', 'in', [$l1])));
        $this->assertSame(['A', 'B', 'C'], $this->labelsFor($this->cond('list', 'in', [$l1, $l2])));
        $this->assertSame(['B', 'D'], $this->labelsFor($this->cond('list', 'not_in', [$l1])));
        $this->assertSame(['D'], $this->labelsFor($this->cond('list', 'not_in', [$l1, $l2])));
    }

    public function test_engagement_score_without_column_is_empty_for_everyone(): void
    {
        if (Schema::hasColumn('recipients', 'engagement_score')) {
            $this->markTestSkipped('recipients.engagement_score exists (WP-52 merged).');
        }

        $this->assertSame(['A', 'B', 'C', 'D'], $this->labelsFor($this->cond('contact.engagement_score', 'is_empty')));
        $this->assertSame([], $this->labelsFor($this->cond('contact.engagement_score', 'greater_than', 0)));
    }

    public function test_and_or_nesting(): void
    {
        $tree = $this->tree([
            $this->cond('attribute.VILLE', 'in', ['alger', 'oran']),
            $this->group([
                $this->cond('attribute.AGE', 'greater_than', 40, ['field_type' => 'number']),
                $this->cond('attribute.CLIENT', 'is_true', null, ['field_type' => 'boolean']),
            ], 'or'),
        ]);
        $this->assertSame(['A', 'B'], $this->labels($tree));

        $tree = $this->tree([
            $this->cond('contact.status', 'in', ['suppressed']),
            $this->group([
                $this->cond('attribute.VILLE', 'equals', 'alger'),
                $this->cond('attribute.AGE', 'equals', 30, ['field_type' => 'number']),
            ]),
        ], 'or');
        $this->assertSame(['A', 'C'], $this->labels($tree));

        // §45.5.2 example: opened (none here) AND (VILLE alger OR oran) AND not in list l2.
        $tree = $this->tree([
            $this->group([
                $this->cond('attribute.VILLE', 'in', ['alger']),
                $this->cond('attribute.VILLE', 'in', ['oran']),
            ], 'or'),
            $this->cond('list', 'not_in', [$this->lists['l2']]),
        ]);
        $this->assertSame(['A'], $this->labels($tree));
    }

    public function test_empty_root_matches_all_non_deleted_contacts_including_blocklisted(): void
    {
        $this->assertSame(['A', 'B', 'C', 'D'], $this->labels($this->tree([])));
        $this->assertSame(4, $this->contract()->count($this->tree([])));
    }

    public function test_apply_on_a_nested_builder_keeps_or_groups_parenthesised(): void
    {
        $tree = $this->tree([
            $this->cond('attribute.VILLE', 'equals', 'alger'),
            $this->cond('attribute.VILLE', 'equals', 'oran'),
        ], 'or');

        // WP-57 usage: $query->where(fn ($q) => $compiler->apply($q, $tree)).
        $labels = Recipient::query()
            ->where('company_name', '!=', 'B')
            ->where(fn ($q) => $this->contract()->apply($q, $tree))
            ->pluck('company_name')->all();

        $this->assertSame(['A'], $labels);
    }

    public function test_matches_and_evaluate_agree_with_compile(): void
    {
        $tree = $this->tree([$this->cond('attribute.VILLE', 'equals', 'oran')]);

        foreach (Recipient::query()->get() as $recipient) {
            $expected = $recipient->company_name === 'B';
            $this->assertSame($expected, $this->contract()->matches($recipient, $tree), $recipient->company_name);
            $this->assertSame($expected, $this->compiler()->evaluate($recipient, $tree));
        }
    }

    public function test_constraint_closure(): void
    {
        $closure = $this->compiler()->constraint($this->tree([$this->cond('attribute.EMAIL', 'starts_with', 'bob')]));

        $this->assertSame(['B'], Recipient::query()->where($closure)->pluck('company_name')->all());
    }

    public function test_legacy_v1_tree_is_upgraded(): void
    {
        $v2 = $this->contract()->upgrade([
            'operator' => 'AND',
            'conditions' => [
                ['field' => 'tags', 'operator' => 'includes_any', 'value' => ['vip']],
                ['field' => 'status', 'operator' => 'equals', 'value' => 'active'],
                ['field' => 'last_contacted_at', 'operator' => 'after', 'value' => '-30d'],
                ['operator' => 'OR', 'conditions' => [
                    ['field' => 'custom_fields.VILLE', 'operator' => 'equals', 'value' => 'alger'],
                    ['field' => 'pagejaunes_company_cache.wilaya_label', 'operator' => 'equals', 'value' => 'Oran'],
                ]],
            ],
        ]);

        $this->assertSame(2, $v2['version']);
        $this->assertSame('and', $v2['operator']);
        $this->assertSame(['type' => 'condition', 'field' => 'contact.tags', 'operator' => 'contains_any', 'value' => [$this->tags['vip']]], $v2['children'][0]);
        $this->assertSame(['type' => 'condition', 'field' => 'contact.status', 'operator' => 'in', 'value' => ['active']], $v2['children'][1]);
        $this->assertSame('in_last_days', $v2['children'][2]['operator']);
        $this->assertSame(30, $v2['children'][2]['value']);
        $this->assertSame('or', $v2['children'][3]['operator']);
        $this->assertSame('attribute.VILLE', $v2['children'][3]['children'][0]['field']);
        $this->assertSame('pagejaunes.wilaya', $v2['children'][3]['children'][1]['field']);

        $this->assertSame(['A'], $this->labels($v2));
        // compile() accepts the legacy tree directly.
        $this->assertSame(1, $this->compiler()->compile([
            'operator' => 'AND', 'conditions' => [['field' => 'email', 'operator' => 'contains', 'value' => 'acme']],
        ])->count());
    }
}
