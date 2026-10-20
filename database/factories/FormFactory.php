<?php

namespace Database\Factories;

use App\Modules\Forms\Models\Form;
use App\Modules\Forms\Services\FormService;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Form> */
class FormFactory extends Factory
{
    protected $model = Form::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'status' => Form::ACTIVE,
            'list_ids' => [],
            'fields' => [
                ['attribute' => 'EMAIL', 'label' => 'Email', 'placeholder' => null, 'required' => true, 'help_text' => null],
                ['attribute' => 'PRENOM', 'label' => 'Prénom', 'placeholder' => null, 'required' => false, 'help_text' => null],
            ],
            'gdpr' => FormService::defaultGdpr(),
            'design' => FormService::defaultDesign(),
            'settings' => ['double_opt_in' => false, 'captcha_enabled' => false] + FormService::defaultSettings(),
            'created_by' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => Form::DRAFT]);
    }

    public function inactive(): static
    {
        return $this->state(['status' => Form::INACTIVE]);
    }

    /** @param  array<string, mixed>  $settings */
    public function withSettings(array $settings): static
    {
        return $this->state(fn (array $a) => ['settings' => array_merge($a['settings'], $settings)]);
    }
}
