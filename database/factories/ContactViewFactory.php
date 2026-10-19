<?php

namespace Database\Factories;

use App\Modules\Identity\Models\User;
use App\Modules\Recipients\Views\Models\ContactView;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactView>
 */
class ContactViewFactory extends Factory
{
    protected $model = ContactView::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->words(2, true),
            'filters' => null,
            'columns' => [['key' => 'attribute.PRENOM', 'width' => 160]],
            'sort' => '-contact.created_at',
            'is_shared' => false,
        ];
    }

    public function shared(): static
    {
        return $this->state(['is_shared' => true]);
    }
}
