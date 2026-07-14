<?php

namespace App\Modules\DeliveryEngine\Jobs;

use App\Domain\Enums\MessageStatus;
use App\Domain\Enums\SendAttemptStatus;
use App\Domain\Enums\SuppressionReason;
use App\Modules\DeliveryEngine\Exceptions\SmtpConcurrencyLimitException;
use App\Modules\DeliveryEngine\Models\SendAttempt;
use App\Modules\DeliveryEngine\Models\SmtpAccount;
use App\Modules\DeliveryEngine\Services\FailoverEngine;
use App\Modules\DeliveryEngine\Services\QuotaManager;
use App\Modules\DeliveryEngine\Services\RetryEngine;
use App\Modules\DeliveryEngine\Services\RotationEngine;
use App\Modules\DeliveryEngine\Services\SmtpManagerService;
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
     * @param  list<int>  $excludedAccountIds  accounts already tried and failed for this message (Failover Engine, §17.8)
     */
    public function __construct(
        public readonly int $messageId,
        public readonly array $excludedAccountIds = [],
    ) {
    }

    public function handle(
        SuppressionManager $suppression,
        RotationEngine $rotation,
        QuotaManager $quota,
        SmtpManagerService $smtpManager,
        RetryEngine $retryEngine,
        FailoverEngine $failover,
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

        $account = $rotation->selectAccount(excludeAccountIds: $this->excludedAccountIds);

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

        $email = (new Email())
            ->from($account->from_name ? new Address($account->from_email, $account->from_name) : new Address($account->from_email))
            ->to($recipient->email)
            ->subject($message->subject)
            ->html($message->html_body);

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
}
