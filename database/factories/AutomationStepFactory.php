<?php

namespace Database\Factories;

use App\Modules\Automations\Models\Automation;
use App\Modules\Automations\Models\AutomationStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AutomationStep>
 */
class AutomationStepFactory extends Factory
{
    protected $model = AutomationStep::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'automation_id' => Automation::factory(),
            'parent_step_id' => null,
            'branch' => null,
            'position' => 0,
            'type' => 'end',
            'config' => [],
        ];
    }
}
