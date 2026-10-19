<?php

namespace App\Modules\Recipients\Segments\Contracts;

use App\Modules\Recipients\Models\Recipient;
use App\Modules\Recipients\Segments\Services\RuleTreeCompiler as RuleTreeCompilerService;
use Closure;
use Illuminate\Container\Attributes\Bind;
use Illuminate\Database\Eloquent\Builder;

/**
 * WP-54 convenience surface on top of the frozen §45.9 {@see RuleTreeCompiler}
 * contract (resolvable on its own through the #[Bind] attribute; the
 * AppServiceProvider binds the §45.9 interface).
 */
#[Bind(RuleTreeCompilerService::class)]
interface SegmentCompilerContract extends RuleTreeCompiler
{
    /**
     * A fresh `Recipient::query()` with the tree applied.
     *
     * @param  array<string, mixed>  $tree
     * @return Builder<Recipient>
     */
    public function compile(array $tree): Builder;

    /**
     * The tree as a closure for `$query->where($closure)`.
     *
     * @param  array<string, mixed>  $tree
     * @return Closure(Builder<Recipient>): Builder<Recipient>
     */
    public function constraint(array $tree): Closure;

    /**
     * Alias of {@see RuleTreeCompiler::matches()}.
     *
     * @param  array<string, mixed>  $tree
     */
    public function evaluate(Recipient $recipient, array $tree): bool;

    /**
     * v2 tree as-is, legacy v1 tree upgraded, `null`/[] → empty v2 root.
     *
     * @param  array<string, mixed>|null  $tree
     * @return array<string, mixed>
     */
    public function normalize(?array $tree): array;
}
