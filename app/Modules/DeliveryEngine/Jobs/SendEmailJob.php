<?php

namespace App\Modules\DeliveryEngine\Jobs;

use App\Domain\Enums\MessageStatus;
use App\Domain\Enums\SendAttemptStatus;
use App\Domain\Enums\SuppressionReason;
use App\Modules\DeliveryEngine\Exceptions\SmtpConcurrencyLimitException;
use App\Modules\DeliveryEngine\Models\SendAttempt;
use App\Modules\DeliveryEngine\Models\SmtpAccount;
use App\Modules\DeliveryEngine\Rendering\RenderingPipeline;
use App\Modules\DeliveryEngine\Services\FailoverEngine;
use App\Modules\DeliveryEngine\Services\QuotaManager;
use App\Modules\DeliveryEngine\Services\RetryEngine;
use App\Modules\DeliveryEngine\Services\RotationEngine;
use App\Modules\DeliveryEngine\Services\SmtpManagerContract;
use App\Modules\DeliveryEngine\Support\CustomHeaders;
use App\Modules\Senders\Models\Sender;
use App\Modules\Suppression\Services\SuppressionManager;
use App\Modules\Tracking\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * docs/17-delivery-engine.md §17.3 — end-to-end send sequence, §17.10 —
 * idempotency & safety. Carries only `messageId` to keep queue payloads
 * small (§17.4).
 */
class SendEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Retries are orchestrated manually (re-dispatch with delay), never by
     * the queue's own attempt mechanism — a single execution either
     * completes the message's fate or schedules the next attempt itself.
     */
    public int $tries = 1;

    /**
     * docs/24-queue-management.md §24.1 — dispatched only from
     * ComposerSendService (direct/transactional composition, not chunked
     * campaign dispatch), so it belongs on the highest-priority
     * `smtp-send-high` queue rather than the campaign-throughput queue.
     *
     * Set in the constructor rather than as a typed property — `Queueable`
     * already declares an untyped `$queue`, and redeclaring it with a type
     * is a fatal trait-composition conflict.
     *
     * @param  list<int>  $excludedAccountIds  accounts already tried and failed for this message (Failover Engine, §17.8)
     */
    public function __construct(
        public readonly int $messageId,
        public readonly array $excludedAccountIds = [],
    ) {
        $this->queue = 'smtp-send-high';
    }

    public function handle(
        SuppressionManager $suppression,
        RotationEngine $rotation,
        QuotaManager $quota,
        SmtpManagerContract $smtpManager,
        RetryEngine $retryEngine,
        FailoverEngine $failover,
        RenderingPipeline $rendering,
    ): void {
        $message = Message::find($this->messageId);

        if ($message === null || MessageStatus::from($message->status)->isTerminalOrInFlight()) {
            return;
        }

        $recipient = $message->recipient;

        if ($suppression->isSuppressed($recipient->email)) {
            $message->update(['status' => MessageStatus::Rejected->value, 'last_provider_response' => 'Adresse supprimée.']);

            return;
        }

        $account = $rotation->selectAccount($this->pinnedAccount($message), $this->excludedAccountIds);

        if ($account === null) {
            static::dispatch($this->messageId, $this->excludedAccountIds)->delay(now()->addMinutes(5));

            return;
        }

        $domain = Str::after($recipient->email, '@');

        if (! $quota->isDomainWithinThrottle($account, $domain)) {
            static::dispatch($this->messageId, $this->excludedAccountIds)->delay(now()->addSeconds(30));

            return;
        }

        $attemptNumber = $message->sendAttempts()->count() + 1;
        $quota->reserve($account);
        $quota->reserveDomainSlot($account, $domain);

        $email = $rendering->render($message)->applyTo(
            (new Email())->from($account->from_name ? new Address($account->from_email, $account->from_name) : new Address($account->from_email)),
            $recipient->email,
        );
        $this->applyCampaignEnvelope($email, $message, $account, $recipient->email);
        $this->applyMessageEnvelope($email, $message, $account);

        foreach ($message->draft?->attachments ?? [] as $attachment) {
            $email->attach(
                Storage::disk($attachment->disk)->get($attachment->path),
                $attachment->original_filename,
                $attachment->mime_type,
            );
        }

        $startedAt = microtime(true);

        try {
            $smtpManager->send($account, $email);

            $this->recordAttempt($message, $account->id, $attemptNumber, SendAttemptStatus::Success, null, null, $startedAt);
            $failover->recordAccountSuccess($account);
            $message->update(['smtp_account_id' => $account->id]);
            $message->markStatus(MessageStatus::Accepted);
        } catch (SmtpConcurrencyLimitException) {
            static::dispatch($this->messageId, $this->excludedAccountIds)->delay(now()->addSeconds(10));
        } catch (TransportExceptionInterface $e) {
            $this->handleTransportFailure(
                $e->getMessage(),
                $message,
                $account->id,
                $attemptNumber,
                $recipient->email,
                $retryEngine,
                $failover,
                $startedAt,
            );
        }
    }

    /**
     * Campaign SMTP strategy `single` (`pinned`): every message of the
     * campaign goes through its `single_smtp_account_id` — the rotation only
     * checks that account's eligibility (it waits, never falls back to
     * another one). `null` = automatic rotation.
     */
    private function pinnedAccount(Message $message): ?SmtpAccount
    {
        if ($message->campaign_id === null) {
            return null;
        }

        $campaign = \App\Modules\Campaigns\Models\Campaign::query()->find($message->campaign_id);

        if ($campaign === null
            || $campaign->smtp_strategy !== \App\Modules\Campaigns\Enums\CampaignSmtpStrategy::Pinned->value
            || $campaign->single_smtp_account_id === null) {
            return null;
        }

        return SmtpAccount::query()->find($campaign->single_smtp_account_id);
    }

    private function handleTransportFailure(
        string $errorMessage,
        Message $message,
        int $accountId,
        int $attemptNumber,
        string $recipientEmail,
        RetryEngine $retryEngine,
        FailoverEngine $failover,
        float $startedAt,
    ): void {
        $account = SmtpAccount::find($accountId);

        if ($retryEngine->isAccountLevelFailure($errorMessage)) {
            $this->recordAttempt($message, $accountId, $attemptNumber, SendAttemptStatus::ConnectionError, $errorMessage, null, $startedAt);

            if ($account !== null) {
                $failover->recordAccountFailure($account);
            }

            if ($attemptNumber < $retryEngine->maxAttempts()) {
                static::dispatch($this->messageId, [...$this->excludedAccountIds, $accountId])->delay(now()->addSeconds(5));

                return;
            }

            $message->update(['last_provider_response' => $errorMessage]);
            $message->markStatus(MessageStatus::Failed);

            return;
        }

        $this->recordAttempt($message, $accountId, $attemptNumber, SendAttemptStatus::Failed, $errorMessage, null, $startedAt);

        if ($retryEngine->isPermanentFailure($errorMessage)) {
            $message->update(['last_provider_response' => $errorMessage]);
            $message->markStatus(MessageStatus::HardBounced);
            app(SuppressionManager::class)->suppress($recipientEmail, SuppressionReason::HardBounce, $message->id);

            return;
        }

        if ($attemptNumber >= $retryEngine->maxAttempts()) {
            $message->update(['last_provider_response' => $errorMessage]);
            $message->markStatus(MessageStatus::Failed);

            return;
        }

        $message->update(['last_provider_response' => $errorMessage]);
        static::dispatch($this->messageId, $this->excludedAccountIds)
            ->delay(now()->addSeconds($retryEngine->delayForAttempt($attemptNumber)));
    }

    private function recordAttempt(
        Message $message,
        int $accountId,
        int $attemptNumber,
        SendAttemptStatus $status,
        ?string $providerResponse,
        ?string $errorCode,
        float $startedAt,
    ): void {
        SendAttempt::create([
            'message_id' => $message->id,
            'smtp_account_id' => $accountId,
            'attempt_number' => $attemptNumber,
            'status' => $status->value,
            'provider_response' => $providerResponse,
            'error_code' => $errorCode,
            'attempted_at' => now(),
            'duration_ms' => (int) ((microtime(true) - $startedAt) * 1000),
        ]);
    }

    /**
     * Campaign messages: the From is the campaign's verified sender (docs/43
     * §43.4.14) with the SMTP account kept as the envelope `Sender`, the
     * campaign reply-to (or the sender's) becomes `Reply-To`, and RFC 8058
     * one-click unsubscribe headers point at the signed unsubscribe URL.
     */
    private function applyCampaignEnvelope(Email $email, Message $message, SmtpAccount $account, string $recipientEmail): void
    {
        if ($message->campaign_id === null) {
            return;
        }

        $campaign = \App\Modules\Campaigns\Models\Campaign::query()->find($message->campaign_id);
        if ($campaign === null) {
            return;
        }

        $sender = $campaign->sender_id !== null ? Sender::query()->find($campaign->sender_id) : null;

        $this->applySender($email, $sender, $account);
        $this->applyReplyTo($email, (string) ($campaign->reply_to ?: $sender?->reply_to));

        if (! $message->is_test) {
            $url = app(\App\Modules\Suppression\Support\UnsubscribeUrlGenerator::class)->signedUrlFor($recipientEmail);
            $email->getHeaders()->addTextHeader('List-Unsubscribe', '<'.$url.'>');
            $email->getHeaders()->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
        }
    }

    /**
     * next-sender — non-campaign messages (transactional API, automations):
     * the message's verified sender becomes the From (same envelope rule as
     * campaigns), then its `delivery_options` are applied: Reply-To (else the
     * sender's), Cc, Bcc, custom headers (never a reserved one) and the
     * stored attachments.
     */
    private function applyMessageEnvelope(Email $email, Message $message, SmtpAccount $account): void
    {
        if ($message->campaign_id !== null) {
            return;
        }

        $sender = $message->sender_id !== null ? Sender::query()->find($message->sender_id) : null;
        $options = is_array($message->delivery_options) ? $message->delivery_options : [];

        $this->applySender($email, $sender, $account);
        $this->applyReplyTo($email, (string) ($options['reply_to']['email'] ?? $sender?->reply_to), (string) ($options['reply_to']['name'] ?? ''));

        foreach (['cc' => 'addCc', 'bcc' => 'addBcc'] as $key => $method) {
            foreach ((array) ($options[$key] ?? []) as $entry) {
                $address = (string) ($entry['email'] ?? '');
                if (filter_var($address, FILTER_VALIDATE_EMAIL)) {
                    $email->{$method}(new Address($address, (string) ($entry['name'] ?? '')));
                }
            }
        }

        foreach ((array) ($options['headers'] ?? []) as $name => $value) {
            if (CustomHeaders::isAllowed($name, $value)) {
                $email->getHeaders()->addTextHeader($name, $value);
            }
        }

        // Explicit transactional `text_content` replaces the plain-text part generated from the HTML.
        if (is_string($options['text'] ?? null) && trim($options['text']) !== '') {
            $email->text($options['text']);
        }

        foreach ((array) ($options['attachments'] ?? []) as $file) {
            $disk = Storage::disk((string) ($file['disk'] ?? 'local'));
            $path = (string) ($file['path'] ?? '');
            if ($path !== '' && $disk->exists($path)) {
                $email->attach((string) $disk->get($path), (string) ($file['name'] ?? basename($path)), $file['mime'] ?? null);
            }
        }
    }

    /** A verified sender is the From; the SMTP account stays the envelope `Sender` when it differs. */
    private function applySender(Email $email, ?Sender $sender, SmtpAccount $account): void
    {
        if ($sender === null || ! $sender->isVerified()) {
            return;
        }

        $email->from(new Address($sender->email, (string) $sender->name));
        if (strcasecmp($sender->email, (string) $account->from_email) !== 0) {
            $email->sender(new Address($account->from_email));
        }
    }

    private function applyReplyTo(Email $email, string $replyTo, string $name = ''): void
    {
        $replyTo = trim($replyTo);
        if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $email->replyTo(new Address($replyTo, $name));
        }
    }
}
