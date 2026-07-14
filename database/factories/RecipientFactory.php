<?php

namespace Database\Factories;

use App\Domain\Enums\RecipientSource;
use App\Domain\Enums\RecipientStatus;
use App\Modules\Recipients\Models\Recipient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Recipient>
 */
class RecipientFactory extends Factory
{
    protected $model = Recipient::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'company_name' => fake()->company(),
            'source' => RecipientSource::Manual->value,
            'status' => RecipientStatus::Active->value,
            'custom_fields' => [],
        ];
    }

    public function suppressed(): static
    {
        return $this->state(['status' => RecipientStatus::Suppressed->value]);
    }

    public function fromSource(RecipientSource $source): static
    {
        return $this->state(['source' => $source->value]);
    }
}
