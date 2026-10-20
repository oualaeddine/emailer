<?php

namespace App\Modules\Automations\Services\StepExecutors;

use App\Modules\Automations\Models\AutomationRun;
use App\Modules\Automations\Models\AutomationStep;
use App\Modules\Recipients\Lists\Contracts\ListMembershipService;
use App\Modules\Recipients\Models\Recipient;

/** `remove_from_list`: { list_id } through ListMembershipService. */
class RemoveFromListExecutor implements StepExecutor
{
    public function __construct(private readonly ListMembershipService $membership) {}

    public function execute(AutomationRun $run, AutomationStep $step, Recipient $recipient): StepResult
    {
        $this->membership->remove(AddToListExecutor::list($step), [$recipient->id], 'automation');

        return StepResult::next();
    }
}
