<?php

namespace App\Domain\Enums;

/**
 * docs/04-database-design.md §4.6.
 */
enum ImportSourceType: string
{
    case Csv = 'csv';
    case Excel = 'excel';
    case PageJaunes = 'pagejaunes';
}
