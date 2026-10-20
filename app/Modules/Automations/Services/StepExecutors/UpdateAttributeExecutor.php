<?php

namespace App\Modules\Automations\Services\StepExecutors;

use App\Modules\Automations\Models\AutomationRun;
use App\Modules\Automations\Models\AutomationStep;
use App\Modules\Recipients\Attributes\Contracts\ContactAttributeService;
use App\Modules\Recipients\Models\Recipient;
use Illuminate\Validation\ValidationException;

/** `update_attribute`: { attribute, value } through ContactAttributeService::apply (fires ContactAttributesUpdated). */
class UpdateAttributeExecutor implements StepExecutor
{
    public function __construct(private readonly ContactAttributeService $attributes) {}

    public function execute(AutomationRun $run, AutomationStep $step, Recipient $recipient): StepResult
    {
        $name = strtoupper((string) ($step->config['attribute'] ?? ''));

        if ($name === '' || ! $this->attributes->definitions()->has($name)) {
            throw new StepFailedException("Attribut inconnu : {$name}");
        }

        try {
            $this->attributes->apply($recipient, [$name => $step->config['value'] ?? null], 'automation');
        } catch (ValidationException $e) {
            throw new StepFailedException((string) collect($e->errors())->flatten()->first());
        }

        return StepResult::next();
    }
}
