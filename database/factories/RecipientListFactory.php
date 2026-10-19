<?php

namespace Database\Factories;

use App\Domain\Enums\RecipientListType;
use App\Modules\Recipients\Models\RecipientList;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Use `RecipientListFactory::new()` (the model does not declare HasFactory).
 *
 * @extends Factory<RecipientList>
 */
class RecipientListFactory extends Factory
{
    protected $model = RecipientList::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Liste '.fake()->unique()->words(2, true),
            'description' => null,
            'type' => RecipientListType::Static->value,
        ];
    }
}
