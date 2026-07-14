<?php

namespace Database\Factories;

use App\Modules\Identity\Models\Permission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Permission>
 */
class PermissionFactory extends Factory
{
    protected $model = Permission::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $module = fake()->unique()->word();

        return [
            'name' => "{$module}.".fake()->word(),
            'module' => $module,
            'description' => fake()->sentence(),
        ];
    }
}
