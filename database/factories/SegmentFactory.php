<?php

namespace Database\Factories;

use App\Modules\Recipients\Models\Segment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * docs/45-brevo-api-contracts.md §45.5.2 — standalone v2 segment.
 *
 * @extends Factory<Segment>
 */
class SegmentFactory extends Factory
{
    protected $model = Segment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Segment '.fake()->unique()->words(2, true),
            'description' => null,
            'rules' => ['version' => 2, 'type' => 'group', 'operator' => 'and', 'children' => []],
            'rules_version' => 2,
        ];
    }

    /**
     * @param  array<string, mixed>  $rules
     */
    public function withRules(array $rules): static
    {
        return $this->state(['rules' => $rules, 'rules_version' => isset($rules['version']) ? 2 : 1]);
    }
}
