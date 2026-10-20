<?php

namespace App\Modules\Automations\Services\StepExecutors;

use App\Modules\Automations\Enums\AutomationStepOutcome;
use App\Modules\Automations\Events\AutomationEmailSent;
use App\Modules\Automations\Models\AutomationRun;
use App\Modules\Automations\Models\AutomationStep;
use App\Modules\Automations\Services\AutomationEmailSender;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Templates\Models\Template;

/**
 * `send_email`: { template_id, subject, sender_id, preview_text }. A
 * blocklisted / suppressed contact is skipped (outcome `skipped`), the run
 * continues.
 */
class SendEmailExecutor implements StepExecutor
{
    public function __construct(private readonly AutomationEmailSender $sender) {}

    public function execute(AutomationRun $run, AutomationStep $step, Recipient $recipient): StepResult
    {
        $template = Template::query()->where('uuid', (string) ($step->config['template_id'] ?? ''))->first();

        if ($template === null) {
            throw new StepFailedException('Modèle d’e-mail introuvable.');
        }

        if ($this->sender->isSuppressed($recipient)) {
            return StepResult::next(AutomationStepOutcome::Skipped);
        }

        $message = $this->sender->send($step, $template, $recipient);

        AutomationEmailSent::dispatch($run->id, $step->id, $message->id);

        return StepResult::next(AutomationStepOutcome::Completed, $message->id);
    }
}
