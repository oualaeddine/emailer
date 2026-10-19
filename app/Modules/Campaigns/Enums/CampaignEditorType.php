<?php

namespace App\Modules\Campaigns\Enums;

/**
 * docs/45-brevo-api-contracts.md §45.4.2 — `campaigns.editor_type`.
 */
enum CampaignEditorType: string
{
    case Dnd = 'dnd';
    case Html = 'html';
    case RichText = 'rich_text';
}
