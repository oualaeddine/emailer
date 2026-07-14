<?php

namespace App\Domain\Enums;

/**
 * docs/04-database-design.md §4.5, docs/13-recipient-management.md §13.1.
 */
enum RecipientSource: string
{
    case Manual = 'manual';
    case CsvImport = 'csv_import';
    case ExcelImport = 'excel_import';
    case PageJaunes = 'pagejaunes';
    case InternalContact = 'internal_contact';
}
