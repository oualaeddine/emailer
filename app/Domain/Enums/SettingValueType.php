<?php

namespace App\Domain\Enums;

/**
 * docs/04-database-design.md §4.15 — `settings.value_type`.
 */
enum SettingValueType: string
{
    case String = 'string';
    case Int = 'int';
    case Bool = 'bool';
    case Json = 'json';
}
