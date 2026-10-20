<?php

namespace App\Modules\Recipients\Views\Contracts;

/**
 * WP-54 owns `App\Modules\Recipients\Segments\Contracts\RuleTreeCompiler`
 * (doc 45 §45.9: validate(array): void, apply(Builder, array): Builder).
 * WP-57 depends on the NAME only and resolves it via `app()->bound()`.
 */
final class RuleTreeCompilerName
{
    public const CONTRACT = 'App\\Modules\\Recipients\\Segments\\Contracts\\RuleTreeCompiler';
}
