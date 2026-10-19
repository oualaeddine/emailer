<?php

namespace Database\Factories;

use App\Modules\Templates\Models\Template;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Template>
 */
class TemplateFactory extends Factory
{
    protected $model = Template::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'category' => null,
            'html_content' => '<html><body><p>'.fake()->sentence().'</p></body></html>',
            'is_archived' => false,
            'editor_type' => 'html',
            'scope' => 'marketing',
            'is_gallery' => false,
        ];
    }

    public function gallery(string $category = 'newsletter'): static
    {
        return $this->state(['is_gallery' => true, 'gallery_category' => $category]);
    }

    public function transactional(): static
    {
        return $this->state(['scope' => 'transactional']);
    }
}
