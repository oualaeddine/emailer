<?php

namespace Database\Factories;

use App\Modules\Suppression\Preferences\Models\UnsubscribePage;
use App\Modules\Suppression\Preferences\Services\PageDefaults;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<UnsubscribePage> */
class UnsubscribePageFactory extends Factory
{
    protected $model = UnsubscribePage::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'is_default' => false,
            'content' => PageDefaults::unsubscribeContent(),
            'design' => PageDefaults::design(),
            'created_by' => null,
        ];
    }

    public function default(): static
    {
        return $this->state(['is_default' => true]);
    }
}
