<?php

namespace App\Modules\Automations\Services\StepExecutors;

use App\Modules\Automations\Models\AutomationRun;
use App\Modules\Automations\Models\AutomationStep;
use App\Modules\Recipients\Lists\Contracts\ListMembershipService;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Recipients\Models\RecipientList;

/** `add_to_list`: { list_id } through ListMembershipService (source `automation`, docs/45 §45.9). */
class AddToListExecutor implements StepExecutor
{
    public function __construct(private readonly ListMembershipService $membership) {}

    public function execute(AutomationRun $run, AutomationStep $step, Recipient $recipient): StepResult
    {
        $this->membership->add(self::list($step), [$recipient->id], 'automation');

        return StepResult::next();
    }

    public static function list(AutomationStep $step): RecipientList
    {
        $list = RecipientList::query()->where('uuid', (string) ($step->config['list_id'] ?? ''))->first();

        if ($list === null) {
            throw new StepFailedException('Liste introuvable.');
        }

        return $list;
    }
}
