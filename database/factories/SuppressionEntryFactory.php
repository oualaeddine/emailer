<?php

namespace Database\Factories;

use App\Domain\Enums\SuppressionReason;
use App\Modules\Suppression\Models\SuppressionEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SuppressionEntry>
 */
class SuppressionEntryFactory extends Factory
{
    protected $model = SuppressionEntry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'reason' => SuppressionReason::ManualBlock->value,
            'source_message_id' => null,
            'notes' => null,
            'added_by' => null,
        ];
    }

    public function reason(SuppressionReason $reason): static
    {
        return $this->state(['reason' => $reason->value]);
    }

    public function withNotes(string $notes): static
    {
        return $this->state(['notes' => $notes]);
    }
}
