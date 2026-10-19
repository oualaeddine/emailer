<?php

namespace Database\Factories;

use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Models\Campaign;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Campaign>
 */
class CampaignFactory extends Factory
{
    protected $model = Campaign::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Campagne '.fake()->unique()->words(2, true),
            'type' => 'classic',
            'subject' => fake()->sentence(4),
            'html_body' => '<p>Bonjour {{recipient.first_name|there}}</p><a href="{{unsubscribe_url}}">Se désinscrire</a>',
            'status' => CampaignStatus::Draft->value,
        ];
    }

    public function status(CampaignStatus $status): static
    {
        return $this->state(['status' => $status->value]);
    }

    public function archived(): static
    {
        return $this->state(['status' => CampaignStatus::Completed->value, 'archived_at' => now()]);
    }
}
