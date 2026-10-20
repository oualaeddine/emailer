<?php

namespace App\Modules\Automations\Listeners;

use App\Modules\Automations\Enums\AutomationTriggerType;
use App\Modules\Automations\Services\TriggerDispatcher;
use App\Modules\Recipients\Attributes\Events\ContactAttributesUpdated;

/**
 * Trigger `attribute_changed` — { attribute, to_value|null }: the attribute
 * changed (to `to_value` when set; `null` = any new value).
 */
class StartRunsOnAttributeChange
{
    public function __construct(private readonly TriggerDispatcher $dispatcher) {}

    public function handle(ContactAttributesUpdated $event): void
    {
        $automations = $this->dispatcher->activeAutomations(AutomationTriggerType::AttributeChanged);
        if ($automations->isEmpty() || $event->changes === []) {
            return;
        }

        $changes = array_change_key_case($event->changes, CASE_UPPER);

        foreach ($automations as $automation) {
            $name = strtoupper((string) ($automation->trigger_config['attribute'] ?? ''));
            if ($name === '' || ! array_key_exists($name, $changes)) {
                continue;
            }

            $new = $changes[$name]['new'] ?? null;
            if (! self::valueMatches($automation->trigger_config['to_value'] ?? null, $new)) {
                continue;
            }

            $this->dispatcher->dispatch([$automation], [$event->recipientId], [
                'trigger' => AutomationTriggerType::AttributeChanged->value,
                'attribute' => $name,
            ]);
        }
    }

    public static function valueMatches(mixed $expected, mixed $new): bool
    {
        if ($expected === null || $expected === '') {
            return true;
        }

        if (is_array($new)) {
            return in_array(self::scalar($expected), array_map(self::scalar(...), $new), true);
        }

        if (is_bool($new) || is_bool($expected)) {
            return filter_var($expected, FILTER_VALIDATE_BOOLEAN) === filter_var($new, FILTER_VALIDATE_BOOLEAN);
        }

        return mb_strtolower(self::scalar($expected)) === mb_strtolower(self::scalar($new));
    }

    private static function scalar(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : (string) json_encode($value);
    }
}
