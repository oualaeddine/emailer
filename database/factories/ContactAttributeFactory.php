<?php

namespace Database\Factories;

use App\Modules\Recipients\Attributes\Models\ContactAttribute;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ContactAttribute> */
class ContactAttributeFactory extends Factory
{
    protected $model = ContactAttribute::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = strtoupper(fake()->unique()->lexify('ATTR_????????'));

        return [
            'name' => $name,
            'label' => ucfirst(strtolower($name)),
            'type' => 'text',
            'options' => null,
            'is_system' => false,
            'storage' => 'custom_fields',
            'column' => null,
            'position' => 0,
        ];
    }

    public function ofType(string $type, ?array $options = null): static
    {
        return $this->state(['type' => $type, 'options' => $options]);
    }

    public function choice(string $type = 'category'): static
    {
        return $this->state([
            'type' => $type,
            'options' => [['value' => 'alger', 'label' => 'Alger'], ['value' => 'oran', 'label' => 'Oran']],
        ]);
    }
}
