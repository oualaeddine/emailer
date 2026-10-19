<?php

namespace App\Modules\Templates\Services;

use App\Domain\Enums\MessageStatus;
use App\Domain\Enums\RecipientSource;
use App\Modules\DeliveryEngine\Jobs\SendEmailJob;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Templates\Models\Template;
use App\Modules\Tracking\Models\Message;
use Illuminate\Support\Facades\Schema;

/**
 * docs/45 §45.4.4 — `POST /templates/{id}/test-send`: 1–5 addresses sent
 * through the normal pipeline (Message + SendEmailJob) flagged `is_test`.
 */
class TemplateTestSendService
{
    /**
     * @param  list<string>  $emails
     * @return array{queued: int, message_ids: list<string>, test_sent_at: string}
     */
    public function send(Template $template, array $emails, ?string $subject = null): array
    {
        $subject = $subject !== null && $subject !== '' ? $subject : ($template->subject ?: $template->name);
        $hasIsTest = Schema::hasColumn('messages', 'is_test');
        $ids = [];

        foreach (array_unique(array_map('strtolower', $emails)) as $email) {
            $recipient = Recipient::query()->firstOrCreate(
                ['email' => $email],
                ['source' => RecipientSource::Manual->value, 'status' => 'active'],
            );

            $message = Message::query()->create([
                'recipient_id' => $recipient->id,
                'subject' => '[TEST] '.$subject,
                'html_body' => $template->html_content,
                'status' => MessageStatus::Queued->value,
                'queued_at' => now(),
            ]);

            // `messages.is_test` is added by migration slot B (WP-42); tolerate its absence.
            if ($hasIsTest) {
                $message->forceFill(['is_test' => true])->save();
            }

            SendEmailJob::dispatch($message->id);
            $ids[] = $message->uuid;
        }

        return ['queued' => count($ids), 'message_ids' => $ids, 'test_sent_at' => now()->toIso8601String()];
    }
}
