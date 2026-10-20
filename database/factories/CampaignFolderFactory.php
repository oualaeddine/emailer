<?php

namespace Database\Factories;

use App\Modules\Campaigns\Models\CampaignFolder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CampaignFolder>
 */
class CampaignFolderFactory extends Factory
{
    protected $model = CampaignFolder::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['name' => 'Dossier '.fake()->unique()->word()];
    }
}
