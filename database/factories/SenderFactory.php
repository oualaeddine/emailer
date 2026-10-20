<?php

namespace Database\Factories;

use App\Modules\Senders\Models\Sender;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sender>
 */
class SenderFactory extends Factory
{
    protected $model = Sender::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'domain_id' => null,
            'reply_to' => null,
            'is_default' => false,
            'verification_method' => null,
            'verified_at' => null,
            'verification_sent_at' => null,
            'created_by' => null,
        ];
    }

    public function verified(string $method = 'email_link'): static
    {
        return $this->state(['verification_method' => $method, 'verified_at' => now()]);
    }

    public function default(): static
    {
        return $this->verified()->state(['is_default' => true]);
    }
}
