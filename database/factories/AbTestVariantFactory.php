<?php

namespace Database\Factories;

use App\Modules\Campaigns\Models\AbTestVariant;
use App\Modules\Campaigns\Models\Campaign;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AbTestVariant>
 */
class AbTestVariantFactory extends Factory
{
    protected $model = AbTestVariant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'label' => AbTestVariant::LABEL_A,
            'subject' => fake()->sentence(4),
            'preview_text' => null,
            'html_body' => null,
            'design_json' => null,
            'is_winner' => false,
            'sample_size' => 0,
        ];
    }

    public function label(string $label): static
    {
        return $this->state(['label' => $label]);
    }
}
