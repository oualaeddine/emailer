<?php

namespace App\Modules\Automations\Listeners;

use App\Modules\Automations\Enums\AutomationTriggerType;
use App\Modules\Automations\Services\TriggerDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Trigger `form_submitted` — { form_ids: uuid[] }. Listens to WP-90's
 * `App\Modules\Forms\Events\FormSubmitted` (formId, submissionId,
 * recipientId?, status) and `ContactOptInConfirmed` (recipientId, formId),
 * registered by class name so this module never autoloads them. A run
 * starts when the contact is actually subscribed: status `subscribed`
 * (or `confirmed`), or the double opt-in confirmation — never on
 * `pending_confirmation`.
 */
class StartRunsOnFormSubmission
{
    public const FORM_SUBMITTED = 'App\\Modules\\Forms\\Events\\FormSubmitted';

    public const OPT_IN_CONFIRMED = 'App\\Modules\\Forms\\Events\\ContactOptInConfirmed';

    private const ENTERING_STATUSES = ['subscribed', 'confirmed'];

    public function __construct(private readonly TriggerDispatcher $dispatcher) {}

    public function handle(object $event): void
    {
        if (! in_array((string) ($event->status ?? ''), self::ENTERING_STATUSES, true)) {
            return;
        }

        $this->start((int) $event->formId, $event->recipientId ?? null);
    }

    public function handleOptInConfirmed(object $event): void
    {
        $this->start((int) $event->formId, $event->recipientId ?? null);
    }

    private function start(int $formId, mixed $recipientId): void
    {
        if ($recipientId === null || ! Schema::hasTable('forms')) {
            return;
        }

        $automations = $this->dispatcher->activeAutomations(AutomationTriggerType::FormSubmitted);
        if ($automations->isEmpty()) {
            return;
        }

        $formUuid = DB::table('forms')->where('id', $formId)->value('uuid');

        $matching = $automations->filter(
            fn ($automation): bool => in_array($formUuid, (array) ($automation->trigger_config['form_ids'] ?? []), true),
        );

        $this->dispatcher->dispatch($matching, [(int) $recipientId], [
            'trigger' => AutomationTriggerType::FormSubmitted->value,
            'form_id' => $formUuid,
        ]);
    }
}
