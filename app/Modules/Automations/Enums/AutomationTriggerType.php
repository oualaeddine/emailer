<?php

namespace App\Modules\Automations\Enums;

/** docs/45 §45.7.5 — `AutomationTrigger.type`. */
enum AutomationTriggerType: string
{
    case ListAdded = 'list_added';
    case FormSubmitted = 'form_submitted';
    case CampaignOpened = 'campaign_opened';
    case CampaignClicked = 'campaign_clicked';
    case AttributeChanged = 'attribute_changed';
    case DateAnniversary = 'date_anniversary';
}
