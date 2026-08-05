<?php

namespace Tests\Feature\Modules\DeliveryEngine;

use App\Domain\Enums\MessageStatus;
use App\Domain\Enums\SendAttemptStatus;
use App\Domain\Enums\SuppressionReason;
use App\Modules\DeliveryEngine\Jobs\SendEmailJob;
use App\Modules\DeliveryEngine\Models\SendAttempt;
use App\Modules\DeliveryEngine\Models\SmtpAccount;
use App\Modules\DeliveryEngine\Services\QuotaManager;
use App\Modules\DeliveryEngine\Services\SmtpManagerContract;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Suppression\Services\SuppressionManager;
use App\Modules\Tracking\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\Support\FakeSmtpManager;
use Tests\TestCase;

/**
 * docs/17-delivery-engine.md §17.4-17.8, docs/32-testing.md §32.4 —
 * exercises `SendEmailJob::handle()` end-to-end (rotation, quota,
 * suppression gate, SMTP send, retry, failover) against a fake SMTP
 * transport so no real connection is ever opened.
 */
class SendEmailJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        FakeSmtpManager::reset();
        $this->app->bind(SmtpManagerContract::class, FakeSmtpManager::class);
    }

    protected function tearDown(): void
    {
        FakeSmtpManager::reset();
        parent::tearDown();
    }

    private function createAccount(array $overrides = []): SmtpAccount
    {
        return SmtpAccount::query()->create(array_merge([
            'name' => 'Compte Test',
            'provider' => 'custom',
            'host' => 'smtp.example.com',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'smtp-user',
            'password_encrypted' => 'secret',
            'from_email' => 'no-reply@example.com',
        ], $overrides));
    }

    private function createMessage(string $email, array $overrides = []): Message
    {
        $recipient = Recipient::factory()->create(['email' => $email]);

        return Message::factory()->create(array_merge(['recipient_id' => $recipient->id], $overrides));
    }

    private function runJob(int $messageId, array $excludedAccountIds = []): void
    {
        $job = new SendEmailJob($messageId, $excludedAccountIds);
        $this->app->call([$job, 'handle']);
    }

    public function test_successful_send_marks_message_sent_records_attempt_and_consumes_quota(): void
    {
        $account = $this->createAccount(['minute_quota' => 1]);
        $message = $this->createMessage('jane@example.com');

        Bus::fake();

        $this->runJob($message->id);

        $this->assertCount(1, FakeSmtpManager::$sent);
        $this->assertSame($account->id, FakeSmtpManager::$sent[0]['account_id']);
        $this->assertSame('jane@example.com', FakeSmtpManager::$sent[0]['to']);

        $message->refresh();
        $this->assertSame(MessageStatus::Accepted->value, $message->status);
        $this->assertNotNull($message->sent_at);
        $this->assertSame($account->id, $message->smtp_account_id);

        $this->assertDatabaseHas('send_attempts', [
            'message_id' => $message->id,
            'smtp_account_id' => $account->id,
            'attempt_number' => 1,
            'status' => SendAttemptStatus::Success->value,
        ]);

        // docs/19-throttling.md §19.3 — reservation is atomic-counter based
        // (Cache), not a `quota_ledger` row written by this job; see final
        // report for the discrepancy against the QuotaManager docblock.
        $quota = $this->app->make(QuotaManager::class);
        $this->assertFalse($quota->isWithinQuota($account->refresh(), null));

        Bus::assertNothingDispatched();
    }

    public function test_suppressed_recipient_skips_send_and_marks_message_rejected(): void
    {
        $account = $this->createAccount();
        $message = $this->createMessage('blocked@example.com');
        $this->app->make(SuppressionManager::class)
            ->suppress('blocked@example.com', SuppressionReason::ManualBlock);

        Bus::fake();

        $this->runJob($message->id);

        $this->assertSame([], FakeSmtpManager::$sent);

        $message->refresh();
        $this->assertSame(MessageStatus::Rejected->value, $message->status);
        $this->assertSame('Adresse supprimée.', $message->last_provider_response);
        $this->assertNull($message->sent_at);

        $this->assertDatabaseCount('send_attempts', 0);
        Bus::assertNothingDispatched();

        // Suppression is checked before Rotation Engine even runs, so the
        // account's quota is never reserved.
        $quota = $this->app->make(QuotaManager::class);
        $this->assertTrue($quota->isWithinQuota($account->refresh(), null));
    }

    public function test_transient_transport_failure_records_failed_attempt_and_schedules_backoff_retry(): void
    {
        $this->createAccount();
        $message = $this->createMessage('bob@example.com');

        FakeSmtpManager::$shouldSucceed = false;
        // Not an account-level pattern (RetryEngine::isAccountLevelFailure)
        // and not a permanent-failure pattern — exercises the plain
        // exponential-backoff retry branch.
        FakeSmtpManager::$failureMessage = '421 4.3.2 Service temporarily unavailable, please try again later.';

        Bus::fake();

        $this->runJob($message->id);

        $this->assertSame([], FakeSmtpManager::$sent);

        $this->assertDatabaseHas('send_attempts', [
            'message_id' => $message->id,
            'attempt_number' => 1,
            'status' => SendAttemptStatus::Failed->value,
            'provider_response' => FakeSmtpManager::$failureMessage,
        ]);

        $message->refresh();
        // Generic retry branch updates last_provider_response but does not
        // transition message status (still queued, not yet terminal).
        $this->assertSame(MessageStatus::Queued->value, $message->status);
        $this->assertSame(FakeSmtpManager::$failureMessage, $message->last_provider_response);

        Bus::assertDispatched(SendEmailJob::class, function (SendEmailJob $job) use ($message): bool {
            return $job->messageId === $message->id
                && $job->excludedAccountIds === []
                && $job->delay !== null;
        });
    }

    public function test_concurrency_limit_releases_job_back_to_queue_without_recording_attempt_or_failover(): void
    {
        $account = $this->createAccount();
        $message = $this->createMessage('carol@example.com');

        FakeSmtpManager::$throwConcurrencyLimit = true;

        Bus::fake();

        $this->runJob($message->id);

        $this->assertSame([], FakeSmtpManager::$sent);
        $this->assertDatabaseCount('send_attempts', 0);

        $message->refresh();
        $this->assertSame(MessageStatus::Queued->value, $message->status);

        // Discrepancy vs. the task's speculative assumption: the actual
        // handle() catch block for SmtpConcurrencyLimitException neither
        // calls FailoverEngine nor excludes the account — it simply
        // re-releases the same job/account pair back to the queue after a
        // short delay (§17.5 point 5's "released back to queue"), so
        // health_status is untouched and the same account may be retried.
        $this->assertSame('healthy', $account->refresh()->health_status);

        Bus::assertDispatched(SendEmailJob::class, function (SendEmailJob $job) use ($message): bool {
            return $job->messageId === $message->id
                && $job->excludedAccountIds === []
                && $job->delay !== null;
        });
    }

    public function test_account_level_connectivity_failure_degrades_account_and_excludes_it_on_retry(): void
    {
        $account = $this->createAccount();
        $message = $this->createMessage('dave@example.com');

        FakeSmtpManager::$shouldSucceed = false;
        // Default fake failure message matches
        // RetryEngine::isAccountLevelFailure()'s "connection could not be
        // established" pattern, so this is a Failover Engine case, not a
        // plain message-level retry.

        Bus::fake();

        $this->runJob($message->id);

        $this->assertDatabaseHas('send_attempts', [
            'message_id' => $message->id,
            'smtp_account_id' => $account->id,
            'attempt_number' => 1,
            'status' => SendAttemptStatus::ConnectionError->value,
        ]);

        $account->refresh();
        $this->assertSame('degraded', $account->health_status);

        $message->refresh();
        $this->assertSame(MessageStatus::Queued->value, $message->status);
        // Unlike the generic retry branch, the account-level-failure branch
        // does not write last_provider_response when attempts remain.
        $this->assertNull($message->last_provider_response);

        Bus::assertDispatched(SendEmailJob::class, function (SendEmailJob $job) use ($message, $account): bool {
            return $job->messageId === $message->id
                && $job->excludedAccountIds === [$account->id]
                && $job->delay !== null;
        });
    }

    public function test_permanent_failure_hard_bounces_message_and_suppresses_recipient(): void
    {
        $this->createAccount();
        $message = $this->createMessage('ghost@example.com');

        FakeSmtpManager::$shouldSucceed = false;
        FakeSmtpManager::$failureMessage = '550 5.1.1 The email account that you tried to reach does not exist.';

        Bus::fake();

        $this->runJob($message->id);

        $this->assertDatabaseHas('send_attempts', [
            'message_id' => $message->id,
            'attempt_number' => 1,
            'status' => SendAttemptStatus::Failed->value,
        ]);

        $message->refresh();
        $this->assertSame(MessageStatus::HardBounced->value, $message->status);
        $this->assertNotNull($message->bounced_at);
        $this->assertSame(FakeSmtpManager::$failureMessage, $message->last_provider_response);

        $this->assertDatabaseHas('suppression_entries', [
            'email' => 'ghost@example.com',
            'reason' => SuppressionReason::HardBounce->value,
            'source_message_id' => $message->id,
        ]);
        $this->assertTrue($this->app->make(SuppressionManager::class)->isSuppressed('ghost@example.com'));

        Bus::assertNothingDispatched();
    }

    public function test_retries_exhausted_marks_message_failed_without_further_dispatch(): void
    {
        $account = $this->createAccount();
        $message = $this->createMessage('erin@example.com');

        for ($i = 1; $i <= 4; $i++) {
            SendAttempt::create([
                'message_id' => $message->id,
                'smtp_account_id' => $account->id,
                'attempt_number' => $i,
                'status' => SendAttemptStatus::Failed->value,
                'attempted_at' => now(),
            ]);
        }

        FakeSmtpManager::$shouldSucceed = false;
        FakeSmtpManager::$failureMessage = '421 4.3.2 Service temporarily unavailable, please try again later.';

        Bus::fake();

        // 4 attempts already recorded, so this is attempt number 5 = the
        // default retry_policy.max_attempts (RetryEngine::maxAttempts()).
        $this->runJob($message->id);

        $this->assertDatabaseHas('send_attempts', [
            'message_id' => $message->id,
            'attempt_number' => 5,
            'status' => SendAttemptStatus::Failed->value,
        ]);

        $message->refresh();
        $this->assertSame(MessageStatus::Failed->value, $message->status);
        $this->assertNotNull($message->failed_at);
        $this->assertSame(FakeSmtpManager::$failureMessage, $message->last_provider_response);

        Bus::assertNothingDispatched();
    }
}
