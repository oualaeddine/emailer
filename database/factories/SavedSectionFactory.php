<?php

namespace Database\Factories;

use App\Modules\Templates\Sections\Models\SavedSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavedSection>
 */
class SavedSectionFactory extends Factory
{
    protected $model = SavedSection::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $text = fake()->sentence(4);

        return [
            'name' => 'Section '.fake()->unique()->word(),
            'mjml' => '<mj-section><mj-column><mj-text>'.$text.'</mj-text></mj-column></mj-section>',
            'component' => [
                'type' => 'mj-section',
                'tagName' => 'mj-section',
                'components' => [[
                    'type' => 'mj-column',
                    'tagName' => 'mj-column',
                    'components' => [['type' => 'mj-text', 'tagName' => 'mj-text', 'content' => $text]],
                ]],
            ],
            'thumbnail_path' => null,
            'created_by' => null,
        ];
    }
}
