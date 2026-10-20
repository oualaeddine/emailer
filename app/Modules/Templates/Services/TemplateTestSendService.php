<?php

namespace App\Modules\Templates\Services;

use App\Domain\Enums\MessageStatus;
use App\Domain\Enums\RecipientSource;
use App\Modules\DeliveryEngine\Jobs\SendEmailJob;
use App\Modules\DeliveryEngine\Rendering\RenderingPipeline;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Templates\Models\Template;
use Illuminate\Support\Facades\Schema;

/**
 * docs/45 §45.4.4 — `POST /templates/{id}/test-send`: 1–5 addresses sent
 * through the normal pipeline (Message + SendEmailJob) flagged `is_test`.
 */
class TemplateTestSendService
{
    public function __construct(private readonly RenderingPipeline $pipeline) {}

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
            // withTrashed: a soft-deleted contact is reused as-is (not restored). RecipientStatus has no
            // Inactive case, so newly created test contacts keep the default Active status.
            $recipient = Recipient::withTrashed()->firstOrCreate(
                ['email' => $email],
                ['source' => RecipientSource::Manual->value, 'status' => 'active'],
            );

            // Personalise phase of the rendering pipeline: merge tags resolve (no campaign).
            $attributes = [
                'recipient_id' => $recipient->id,
                'status' => MessageStatus::Queued->value,
                'queued_at' => now(),
            ];
            // `messages.is_test` is added by migration slot B (WP-42); tolerate its absence.
            if ($hasIsTest) {
                $attributes['is_test'] = true;
            }
            $message = $this->pipeline->createMessage(
                $attributes,
                '[TEST] '.$subject,
                (string) $template->html_content,
                $recipient,
                campaign: null,
            );

            SendEmailJob::dispatch($message->id);
            $ids[] = $message->uuid;
        }

        return ['queued' => count($ids), 'message_ids' => $ids, 'test_sent_at' => now()->toIso8601String()];
    }
}
