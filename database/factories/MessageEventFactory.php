<?php

namespace Database\Factories;

use App\Modules\Tracking\Models\Message;
use App\Modules\Tracking\Models\MessageEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MessageEvent>
 */
class MessageEventFactory extends Factory
{
    protected $model = MessageEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'message_id' => Message::factory(),
            'campaign_id' => fn (array $attributes) => Message::query()->whereKey($attributes['message_id'])->value('campaign_id'),
            'recipient_id' => fn (array $attributes) => Message::query()->whereKey($attributes['message_id'])->value('recipient_id'),
            'event_type' => MessageEvent::OPENED,
            'occurred_at' => now(),
            'is_proxy_open' => false,
        ];
    }

    public function forMessage(Message $message): static
    {
        return $this->state([
            'message_id' => $message->id,
            'campaign_id' => $message->campaign_id,
            'recipient_id' => $message->recipient_id,
        ]);
    }

    public function opened(array $attributes = []): static
    {
        return $this->state(['event_type' => MessageEvent::OPENED] + $attributes);
    }

    public function clicked(string $url = 'https://example.com/', array $attributes = []): static
    {
        return $this->state([
            'event_type' => MessageEvent::CLICKED,
            'url' => $url,
            'link_id' => substr(sha1($url), 0, 16),
        ] + $attributes);
    }

    public function proxyOpen(): static
    {
        return $this->state(['event_type' => MessageEvent::OPENED, 'is_proxy_open' => true, 'user_agent' => 'Mozilla/5.0']);
    }
}
