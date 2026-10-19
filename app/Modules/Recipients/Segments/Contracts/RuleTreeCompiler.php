<?php

namespace App\Modules\Recipients\Segments\Contracts;

use App\Modules\Recipients\Models\Recipient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * docs/45-brevo-api-contracts.md §45.9 — compiles a rule tree (grammar v2,
 * §45.5.2) into constraints on `recipients`. Bound in AppServiceProvider
 * (WP-54). Consumers: WP-57 filters, WP-42 segment audiences, WP-72,
 * WP-91, WP-61/63.
 *
 * Every method accepts a v2 tree or a legacy v1 tree (doc 13 §13.7, no
 * `version` key), which is upgraded first. Soft-deleted recipients are
 * always excluded; blocklisted / suppressed contacts are NOT excluded.
 */
interface RuleTreeCompiler
{
    /**
     * @param  array<string, mixed>  $tree
     * @param  string  $path  dot-path prefix of the tree in the request (error keys: "{$path}.children.0.value")
     *
     * @throws ValidationException
     */
    public function validate(array $tree, string $path = 'rules'): void;

    /**
     * Adds the tree's constraints to `$recipients` (a `recipients` builder,
     * possibly nested) wrapped in one parenthesised group, and returns it.
     * Validates first.
     *
     * @param  Builder<Recipient>  $recipients
     * @param  array<string, mixed>  $tree
     * @return Builder<Recipient>
     *
     * @throws ValidationException
     */
    public function apply(Builder $recipients, array $tree): Builder;

    /**
     * COUNT(*) of matching (non soft-deleted) recipients, no materialisation.
     *
     * @param  array<string, mixed>  $tree
     */
    public function count(array $tree): int;

    /**
     * @param  array<string, mixed>  $tree
     */
    public function matches(Recipient $recipient, array $tree): bool;

    /**
     * Converts a legacy v1 tree (doc 13 §13.7) to grammar v2.
     *
     * @param  array<string, mixed>  $legacyTree
     * @return array<string, mixed>
     */
    public function upgrade(array $legacyTree): array;
}
