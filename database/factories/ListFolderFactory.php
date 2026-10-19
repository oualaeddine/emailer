<?php

namespace Database\Factories;

use App\Modules\Recipients\Lists\Models\ListFolder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ListFolder>
 */
class ListFolderFactory extends Factory
{
    protected $model = ListFolder::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['name' => 'Dossier '.fake()->unique()->word(), 'kind' => 'list'];
    }

    public function segment(): static
    {
        return $this->state(['kind' => 'segment']);
    }
}
