<?php

namespace Database\Factories;

use App\Modules\Suppression\Preferences\Models\UpdateProfileForm;
use App\Modules\Suppression\Preferences\Services\PageDefaults;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<UpdateProfileForm> */
class UpdateProfileFormFactory extends Factory
{
    protected $model = UpdateProfileForm::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'is_default' => false,
            'fields' => [['attribute' => 'PRENOM', 'label' => 'Prénom', 'required' => false]],
            'list_ids' => [],
            'content' => PageDefaults::profileContent(),
            'design' => PageDefaults::design(),
            'created_by' => null,
        ];
    }

    public function default(): static
    {
        return $this->state(['is_default' => true]);
    }
}
