<?php

namespace Database\Factories;

use App\Modules\Automations\Enums\AutomationStatus;
use App\Modules\Automations\Models\Automation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Automation>
 */
class AutomationFactory extends Factory
{
    protected $model = Automation::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => 'Automatisation '.fake()->unique()->words(2, true),
            'description' => null,
            'status' => AutomationStatus::Draft->value,
            'trigger_type' => 'list_added',
            'trigger_config' => ['list_ids' => []],
            'allow_reentry' => false,
            'reentry_after_days' => null,
        ];
    }

    /** @param  array<string, mixed>  $config */
    public function trigger(string $type, array $config): static
    {
        return $this->state(['trigger_type' => $type, 'trigger_config' => $config]);
    }

    public function active(): static
    {
        return $this->state(['status' => AutomationStatus::Active->value, 'activated_at' => now()]);
    }

    public function paused(): static
    {
        return $this->state(['status' => AutomationStatus::Paused->value]);
    }
}
