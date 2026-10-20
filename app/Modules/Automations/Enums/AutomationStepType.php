<?php

namespace App\Modules\Automations\Enums;

/** docs/45 §45.7.5 — `AutomationStep.type`. */
enum AutomationStepType: string
{
    case SendEmail = 'send_email';
    case Wait = 'wait';
    case Condition = 'condition';
    case AddToList = 'add_to_list';
    case RemoveFromList = 'remove_from_list';
    case UpdateAttribute = 'update_attribute';
    case End = 'end';
}
