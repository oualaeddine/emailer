<?php

namespace Database\Factories;

use App\Modules\Webhooks\Models\ApiWebhook;
use App\Modules\Webhooks\Models\ApiWebhookDelivery;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ApiWebhookDelivery> */
class ApiWebhookDeliveryFactory extends Factory
{
    protected $model = ApiWebhookDelivery::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'api_webhook_id' => ApiWebhook::factory(),
            'event' => 'delivered',
            'status' => ApiWebhookDelivery::PENDING,
            'attempts' => 0,
            'payload' => ['event' => 'delivered', 'email' => 'amina@exemple.dz'],
        ];
    }

    public function status(string $status): static
    {
        return $this->state(['status' => $status]);
    }
}
