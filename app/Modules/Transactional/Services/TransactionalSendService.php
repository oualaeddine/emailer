<?php

namespace App\Modules\Transactional\Services;

use App\Domain\Enums\MessageStatus;
use App\Domain\Enums\RecipientSource;
use App\Domain\Enums\RecipientStatus;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\DeliveryEngine\Jobs\SendEmailJob;
use App\Modules\DeliveryEngine\Rendering\RenderingPipeline;
use App\Modules\DeliveryEngine\Support\CustomHeaders;
use App\Modules\Identity\Models\User;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Senders\Models\Sender;
use App\Modules\Templates\Models\Template;
use App\Modules\Tracking\Models\Message;
use App\Modules\Tracking\Services\TrackingEventRecorder;
use App\Modules\Transactional\Contracts\TransactionalSender;
use App\Modules\Transactional\Events\TransactionalMessageQueued;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * docs/45 §45.7.6 / §45.9 — implementation of {@see TransactionalSender}.
 *
 * The transactional nature of a message is stored as a `queued` event of
 * `message_events` whose metadata holds `source`, `tags`, `template_id`,
 * `sender_email`, `api_key_id`… (no column is added to `messages`, §45.8).
 * next-sender: each message also stores its verified sender (`sender_id`)
 * and its `delivery_options` (reply-to, cc, bcc, headers, attachments),
 * applied by SendEmailJob.
 */
class TransactionalSendService implements TransactionalSender
{
    public const QUEUED_EVENT = 'queued';

    public const MAX_RECIPIENTS = 50;

    public function __construct(
        private readonly RenderingPipeline $rendering,
        private readonly TrackingEventRecorder $events,
        private readonly AuditLogger $audit,
        private readonly TransactionalAttachmentStore $attachments,
    ) {}

    public function send(array $input, array $context = []): array
    {
        $source = (string) ($context['source'] ?? 'api');
        $actor = ($context['actor'] ?? null) instanceof User ? $context['actor'] : null;
        $apiKeyId = isset($context['api_key_id']) ? (int) $context['api_key_id'] : null;

        $to = $this->recipients($input['to'] ?? []);
        [$subject, $html, $template] = $this->content($input, $source);
        $sender = $this->resolveSender($input);
        $params = is_array($input['params'] ?? null) ? $input['params'] : [];
        $subject = $this->mergeParams($subject, $params, false);
        $html = $this->mergeParams($html, $params, true);
        $scheduledAt = ! empty($input['scheduled_at']) ? Carbon::parse($input['scheduled_at']) : null;
        $replyTo = $input['reply_to']['email'] ?? $sender?->reply_to;
        $headers = $this->headers($input['headers'] ?? null);
        $files = $this->attachments->decode(is_array($input['attachments'] ?? null) ? $input['attachments'] : []);

        $meta = array_filter([
            'source' => $source,
            'transactional' => true,
            'tags' => array_values(array_unique(array_map('strval', $input['tags'] ?? []))),
            'template_id' => $template?->uuid,
            'sender_id' => $sender?->uuid,
            'sender_email' => $sender?->email ?? ($input['sender']['email'] ?? null),
            'reply_to' => $replyTo,
            'api_key_id' => $apiKeyId,
            'cc' => array_column($input['cc'] ?? [], 'email'),
            'bcc' => array_column($input['bcc'] ?? [], 'email'),
            'headers' => $input['headers'] ?? null,
            'attachments' => array_column($input['attachments'] ?? [], 'name'),
        ], fn ($v) => $v !== null && $v !== []);

        $stepColumn = Schema::hasColumn('messages', 'automation_step_id');

        // Attachments are stored once, under the first message's uuid, and shared by every message of the request.
        $firstUuid = (string) Str::uuid();
        $options = array_filter([
            'reply_to' => filled($input['reply_to']['email'] ?? null)
                ? ['email' => (string) $input['reply_to']['email'], 'name' => $input['reply_to']['name'] ?? null]
                : null,
            'cc' => $this->addresses($input['cc'] ?? []),
            'bcc' => $this->addresses($input['bcc'] ?? []),
            'headers' => $headers,
            'attachments' => $this->attachments->store($files, $firstUuid),
        ], fn ($v) => $v !== null && $v !== []);

        try {
            $messages = DB::transaction(function () use ($to, $subject, $html, $meta, $context, $stepColumn, $sender, $options, $firstUuid): array {
                $out = [];
                foreach ($to as $address) {
                    $recipient = Recipient::withTrashed()->where('email', $address['email'])->first()
                        ?? Recipient::query()->create([
                            'email' => $address['email'],
                            'first_name' => $address['name'] ?: null,
                            'source' => RecipientSource::Api->value,
                            'status' => RecipientStatus::Active->value,
                            'custom_fields' => [],
                        ]);

                    $attributes = [
                        'uuid' => $out === [] ? $firstUuid : null,
                        'campaign_id' => null,
                        'recipient_id' => $recipient->id,
                        'status' => MessageStatus::Queued->value,
                        'queued_at' => now(),
                        'is_test' => false,
                        'sender_id' => $sender?->id,
                        'delivery_options' => $options !== [] ? $options : null,
                    ];
                    if ($stepColumn && ! empty($context['automation_step_id'])) {
                        $attributes['automation_step_id'] = (int) $context['automation_step_id'];
                    }

                    $message = $this->rendering->createMessage($attributes, $subject, $html, $recipient);
                    $this->events->recordEvent($message, self::QUEUED_EVENT, $meta);
                    $out[] = $message;
                }

                return $out;
            });
        } catch (\Throwable $e) {
            if ($files !== []) {
                $this->attachments->delete($firstUuid);
            }
            throw $e;
        }

        foreach ($messages as $message) {
            $job = new SendEmailJob($message->id);
            if ($scheduledAt !== null && $scheduledAt->isFuture()) {
                dispatch($job)->delay($scheduledAt);
            } else {
                dispatch($job);
            }
            TransactionalMessageQueued::dispatch($message->id, $apiKeyId);
        }

        if ($source === 'api') {
            $this->audit->record('transactional.sent', $messages[0], $actor, null, [
                'count' => count($messages),
                'message_ids' => array_map(fn (Message $m): string => $m->uuid, $messages),
                'api_key_id' => $apiKeyId,
                'template_id' => $template?->uuid,
            ]);
        }

        return $messages;
    }

    /**
     * @param  array<int, mixed>  $to
     * @return list<array{email: string, name: ?string}>
     */
    private function recipients(array $to): array
    {
        $unique = [];
        foreach ($to as $entry) {
            $email = strtolower(trim((string) ($entry['email'] ?? '')));
            if ($email !== '') {
                $unique[$email] ??= ['email' => $email, 'name' => $entry['name'] ?? null];
            }
        }

        if ($unique === []) {
            throw ValidationException::withMessages(['to' => 'Au moins un destinataire est requis.']);
        }
        if (count($unique) > self::MAX_RECIPIENTS) {
            throw ValidationException::withMessages(['to' => 'Au maximum '.self::MAX_RECIPIENTS.' destinataires par requête.']);
        }

        return array_values($unique);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{0: string, 1: string, 2: ?Template}
     */
    private function content(array $input, string $source = 'api'): array
    {
        $hasHtml = filled($input['html_content'] ?? null);
        $hasTemplate = filled($input['template_id'] ?? null);

        if ($hasHtml === $hasTemplate) {
            throw ValidationException::withMessages([
                $hasHtml ? 'template_id' : 'html_content' => 'Fournissez soit html_content, soit template_id (un seul des deux).',
            ]);
        }

        $template = null;
        $html = (string) ($input['html_content'] ?? '');
        if ($hasTemplate) {
            $template = Template::query()
                ->where('uuid', (string) $input['template_id'])
                // Automation steps may send any (marketing) template; the API is transactional-only.
                ->when($source !== 'automation', fn ($q) => $q->where('scope', 'transactional'))
                ->where('is_archived', false)
                ->first();
            if ($template === null) {
                throw ValidationException::withMessages(['template_id' => 'Modèle transactionnel introuvable.']);
            }
            $html = (string) $template->html_content;
        }

        // Optional hidden preheader (used by automation steps' `preview_text`).
        $preview = trim((string) ($input['preview_text'] ?? ''));
        if ($preview !== '') {
            $div = '<div style="display:none;max-height:0;overflow:hidden;mso-hide:all">'.e($preview).'</div>';
            $count = 0;
            $html = preg_replace('/<body\b[^>]*>/i', '$0'.$div, $html, 1, $count) ?? $html;
            $html = $count > 0 ? $html : $div.$html;
        }

        $subject = trim((string) ($input['subject'] ?? '')) ?: trim((string) ($template?->subject ?? ''));
        if ($subject === '') {
            throw ValidationException::withMessages(['subject' => 'L’objet est requis.']);
        }

        return [$subject, $html, $template];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function resolveSender(array $input): ?Sender
    {
        if (filled($input['sender_id'] ?? null)) {
            $sender = Sender::query()->where('uuid', (string) $input['sender_id'])->first();
            $field = 'sender_id';
        } elseif (filled($input['sender']['email'] ?? null)) {
            $sender = Sender::query()->where('email', strtolower((string) $input['sender']['email']))->first();
            $field = 'sender.email';
        } else {
            return null;
        }

        if ($sender === null || ! $sender->isVerified()) {
            throw ValidationException::withMessages([$field => 'L’expéditeur doit être un expéditeur vérifié.']);
        }

        return $sender;
    }

    /**
     * Custom headers (name => single-line value); reserved or malformed names are refused.
     *
     * @return array<string, string>
     */
    private function headers(mixed $headers): array
    {
        if ($headers === null || $headers === []) {
            return [];
        }
        if (! is_array($headers)) {
            throw ValidationException::withMessages(['headers' => 'Les en-têtes doivent être un objet nom → valeur.']);
        }

        foreach ($headers as $name => $value) {
            if (! CustomHeaders::isAllowed($name, $value)) {
                throw ValidationException::withMessages([
                    'headers' => is_string($name) && CustomHeaders::isReserved($name)
                        ? "L’en-tête « {$name} » est réservé et ne peut pas être personnalisé."
                        : 'En-tête invalide : '.(string) $name.'.',
                ]);
            }
        }

        return $headers;
    }

    /**
     * @param  mixed  $entries  [{email, name?}]
     * @return list<array{email: string, name: ?string}>
     */
    private function addresses(mixed $entries): array
    {
        $out = [];
        foreach (is_array($entries) ? $entries : [] as $entry) {
            $email = strtolower(trim((string) ($entry['email'] ?? '')));
            if ($email !== '') {
                $out[$email] ??= ['email' => $email, 'name' => filled($entry['name'] ?? null) ? (string) $entry['name'] : null];
            }
        }

        return array_values($out);
    }

    /**
     * Replaces `{{ params.KEY }}` (HTML-escaped in the body, raw in the subject).
     *
     * @param  array<string, mixed>  $params
     */
    private function mergeParams(string $text, array $params, bool $escape): string
    {
        return (string) preg_replace_callback('/\{\{\s*params\.([A-Za-z0-9_]+)\s*\}\}/', function (array $m) use ($params, $escape): string {
            $value = $params[$m[1]] ?? '';
            $value = is_scalar($value) ? (string) $value : (string) json_encode($value, JSON_UNESCAPED_UNICODE);

            return $escape ? e($value) : $value;
        }, $text);
    }
}
