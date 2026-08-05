<?php

namespace Database\Factories;

use App\Domain\Enums\MessageStatus;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Tracking\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    protected $model = Message::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'recipient_id' => Recipient::factory(),
            'subject' => fake()->sentence(),
            'html_body' => '<p>'.fake()->paragraph().'</p>',
            'status' => MessageStatus::Queued->value,
            'queued_at' => now(),
        ];
    }
}
