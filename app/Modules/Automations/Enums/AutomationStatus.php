<?php

namespace App\Modules\Automations\Enums;

/** docs/45 §45.7.5 — `Automation.status` (labels fr.automations.statusLabels). */
enum AutomationStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Paused = 'paused';
}
