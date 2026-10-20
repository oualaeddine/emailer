<?php

namespace Database\Factories;

use App\Modules\Webhooks\Models\ApiWebhook;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ApiWebhook> */
class ApiWebhookFactory extends Factory
{
    protected $model = ApiWebhook::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'url' => 'https://hooks.exemple.dz/'.Str::random(8),
            'description' => null,
            'type' => ApiWebhook::TYPE_TRANSACTIONAL,
            'events' => ['delivered', 'click', 'hardBounce'],
            'secret' => 'whsec_'.Str::random(40),
            'headers' => null,
            'is_active' => true,
        ];
    }

    public function marketing(array $events = ['delivered', 'opened', 'click']): static
    {
        return $this->state(['type' => ApiWebhook::TYPE_MARKETING, 'events' => $events]);
    }

    /** @param list<string> $events */
    public function events(array $events): static
    {
        return $this->state(['events' => $events]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
