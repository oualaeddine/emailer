<?php

namespace App\Modules\Campaigns\Enums;

/**
 * docs/45-brevo-api-contracts.md §45.4.2 — `available_actions` values (TS `CampaignAction`).
 */
enum CampaignAction: string
{
    case Edit = 'edit';
    case Report = 'report';
    case Duplicate = 'duplicate';
    case TestSend = 'test_send';
    case Schedule = 'schedule';
    case Unschedule = 'unschedule';
    case SendNow = 'send_now';
    case Pause = 'pause';
    case Resume = 'resume';
    case Cancel = 'cancel';
    case SendToNewContacts = 'send_to_new_contacts';
    case Archive = 'archive';
    case Unarchive = 'unarchive';
    case Move = 'move';
    case Delete = 'delete';
}
