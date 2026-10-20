<?php

namespace App\Modules\Automations\Services;

use App\Domain\Enums\MessageStatus;
use App\Domain\Enums\RecipientStatus;
use App\Modules\Automations\Models\AutomationStep;
use App\Modules\DeliveryEngine\Jobs\SendEmailJob;
use App\Modules\DeliveryEngine\Rendering\RenderingPipeline;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Templates\Models\Template;
use App\Modules\Tracking\Models\Message;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * docs/45 §45.7.5 — e-mails of `send_email` steps go through the
 * transactional path: WP-100's `TransactionalSender` contract when it is
 * bound (context `source = automation`, `automation_step_id`), otherwise
 * the fallback — a non-campaign `Message` (campaign null, `is_test` false,
 * `automation_step_id` set) personalised by the rendering pipeline and
 * queued with `SendEmailJob`.
 */
class AutomationEmailSender
{
    /** WP-100 — `send(array $input, array $context = []): list<Message>`. */
    public const TRANSACTIONAL_SENDER = 'App\\Modules\\Transactional\\Contracts\\TransactionalSender';

    private static ?bool $hasStepColumn = null;

    public function __construct(private readonly RenderingPipeline $rendering) {}

    public function isSuppressed(Recipient $recipient): bool
    {
        return $recipient->trashed()
            || $recipient->status === RecipientStatus::Suppressed->value
            || DB::table('suppression_entries')->where('email', $recipient->email)->exists();
    }

    public function send(AutomationStep $step, Template $template, Recipient $recipient): Message
    {
        $config = $step->config;
        $subject = trim((string) ($config['subject'] ?? '')) !== ''
            ? (string) $config['subject']
            : (trim((string) ($template->subject ?? '')) !== '' ? (string) $template->subject : (string) $template->name);

        if (app()->bound(self::TRANSACTIONAL_SENDER)) {
            return $this->sendThroughContract($step, $template, $recipient, $subject);
        }

        $html = $this->withPreheader((string) $template->html_content, $config['preview_text'] ?? null);

        $attributes = [
            'campaign_id' => null,
            'recipient_id' => $recipient->id,
            'status' => MessageStatus::Queued->value,
            'queued_at' => now(),
            'is_test' => false,
        ];

        if (self::$hasStepColumn ??= Schema::hasColumn('messages', 'automation_step_id')) {
            $attributes['automation_step_id'] = $step->id;
        }

        $message = $this->rendering->createMessage($attributes, $subject, $html, $recipient, null);

        SendEmailJob::dispatch($message->id);

        return $message;
    }

    private function sendThroughContract(AutomationStep $step, Template $template, Recipient $recipient, string $subject): Message
    {
        $name = trim(((string) $recipient->first_name).' '.((string) $recipient->last_name));
        $input = array_filter([
            'to' => [array_filter(['email' => $recipient->email, 'name' => $name !== '' ? $name : null])],
            'template_id' => $template->uuid,
            'subject' => $subject,
            'sender_id' => $step->config['sender_id'] ?? null,
            'tags' => ['automation'],
        ], fn ($value): bool => $value !== null);

        $messages = app(self::TRANSACTIONAL_SENDER)->send($input, [
            'source' => 'automation',
            'automation_step_id' => $step->id,
            'actor' => null,
        ]);

        $message = collect($messages)->first();
        if (! $message instanceof Message) {
            throw new \RuntimeException('L’envoi transactionnel n’a créé aucun message.');
        }

        return $message;
    }

    /** Hidden preheader (no campaign carries `preview_text` on this path). */
    private function withPreheader(string $html, mixed $previewText): string
    {
        $text = trim((string) $previewText);
        if ($text === '') {
            return $html;
        }

        $div = '<div style="display:none;max-height:0;overflow:hidden;mso-hide:all">'.e($text).'</div>';

        $count = 0;
        $html = preg_replace('/<body\b[^>]*>/i', '$0'.$div, $html, 1, $count) ?? $html;

        return $count > 0 ? $html : $div.$html;
    }
}
