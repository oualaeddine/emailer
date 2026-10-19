<?php

namespace Tests\Feature\Modules\Recipients\Segments;

use App\Modules\Recipients\Segments\Contracts\RuleTreeCompiler;
use App\Modules\Recipients\Segments\Contracts\SegmentCompilerContract;

/**
 * Helpers for docs/45 §45.5.2 rule trees in tests.
 */
trait BuildsRuleTrees
{
    /**
     * @param  array<int, array<string, mixed>>  $children
     * @return array<string, mixed>
     */
    protected function tree(array $children, string $operator = 'and'): array
    {
        return ['version' => 2, 'type' => 'group', 'operator' => $operator, 'children' => $children];
    }

    /**
     * @param  array<int, array<string, mixed>>  $children
     * @return array<string, mixed>
     */
    protected function group(array $children, string $operator = 'and'): array
    {
        return ['type' => 'group', 'operator' => $operator, 'children' => $children];
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function cond(string $field, string $operator, mixed $value = null, array $extra = []): array
    {
        return ['type' => 'condition', 'field' => $field, 'operator' => $operator, 'value' => $value] + $extra;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function engagement(array $overrides = []): array
    {
        return $overrides + [
            'campaign_ids' => null, 'within_days' => null, 'from' => null, 'to' => null,
            'link_url' => null, 'min_count' => null, 'automation_step_id' => null,
        ];
    }

    protected function compiler(): SegmentCompilerContract
    {
        return app(SegmentCompilerContract::class);
    }

    protected function contract(): RuleTreeCompiler
    {
        return app(RuleTreeCompiler::class);
    }

    /**
     * Sorted `company_name` labels of the recipients matching one condition.
     *
     * @param  array<string, mixed>  $condition
     * @return list<string>
     */
    protected function labelsFor(array $condition): array
    {
        return $this->labels($this->tree([$condition]));
    }

    /**
     * @param  array<string, mixed>  $tree
     * @return list<string>
     */
    protected function labels(array $tree): array
    {
        $labels = $this->compiler()->compile($tree)->pluck('company_name')->all();
        sort($labels);

        return $labels;
    }
}
