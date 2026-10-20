<?php

namespace Database\Factories;

use App\Modules\Forms\Models\Form;
use App\Modules\Forms\Models\FormSubmission;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FormSubmission> */
class FormSubmissionFactory extends Factory
{
    protected $model = FormSubmission::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'form_id' => Form::factory(),
            'email' => fake()->unique()->safeEmail(),
            'status' => 'subscribed',
            'data' => [],
            'submitted_at' => now(),
        ];
    }
}
