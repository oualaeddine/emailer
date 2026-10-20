<?php

namespace App\Modules\Automations\Listeners;

use App\Modules\Automations\Enums\AutomationTriggerType;
use App\Modules\Automations\Services\TriggerDispatcher;
use App\Modules\Recipients\Lists\Events\RecipientsAddedToList;
use Illuminate\Support\Facades\DB;

/** Trigger `list_added` — { list_ids: uuid[] }. */
class StartRunsOnListAddition
{
    public function __construct(private readonly TriggerDispatcher $dispatcher) {}

    public function handle(RecipientsAddedToList $event): void
    {
        $automations = $this->dispatcher->activeAutomations(AutomationTriggerType::ListAdded);
        if ($automations->isEmpty() || $event->recipientIds === []) {
            return;
        }

        $listUuid = DB::table('recipient_lists')->where('id', $event->listId)->value('uuid');

        $matching = $automations->filter(
            fn ($automation): bool => in_array($listUuid, (array) ($automation->trigger_config['list_ids'] ?? []), true),
        );

        $this->dispatcher->dispatch($matching, $event->recipientIds, [
            'trigger' => AutomationTriggerType::ListAdded->value,
            'list_id' => $listUuid,
            'source' => $event->source,
        ]);
    }
}
