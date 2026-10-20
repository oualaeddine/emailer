<?php

namespace App\Modules\Automations\Http\Requests;

/** docs/45 §45.7.5 — PATCH: partial `AutomationInput`, never steps. */
class UpdateAutomationRequest extends StoreAutomationRequest
{
    protected bool $partial = true;
}
