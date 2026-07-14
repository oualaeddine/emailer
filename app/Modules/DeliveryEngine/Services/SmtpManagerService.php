<?php

namespace App\Modules\DeliveryEngine\Services;

use App\Modules\DeliveryEngine\Exceptions\SmtpConcurrencyLimitException;
use App\Modules\DeliveryEngine\Models\SmtpAccount;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Email;

/**
 * docs/17-delivery-engine.md §17.6 — SMTP Manager: owns actual SMTP
 * transport, connection reuse up to `max_messages_per_connection` and
 * `max_concurrent_connections`. Connection reuse is scoped to this queue
 * worker process's lifetime (a persistent pool held in a static, per the
 * documented "concurrency limits" being a worker-level concern — a fresh
 * PHP-FPM/CLI process for each queue worker naturally resets the pool).
 */
class SmtpManagerService
{
    /** @var array<int, EsmtpTransport> */
    private static array $pool = [];

    /** @var array<int, int> */
    private static array $messagesOnConnection = [];

    public function __construct(
        private readonly SmtpTransportFactory $transportFactory,
        private readonly QuotaManager $quota,
    ) {
    }

    /**
     * @throws SmtpConcurrencyLimitException
     */
    public function send(SmtpAccount $account, Email $email): void
    {
        if (! $this->quota->acquireConnectionSlot($account)) {
            throw new SmtpConcurrencyLimitException($account);
        }

        try {
            $transport = $this->connectionFor($account);
            $transport->send($email);

            self::$messagesOnConnection[$account->id] = (self::$messagesOnConnection[$account->id] ?? 0) + 1;

            if ($account->max_messages_per_connection !== null
                && self::$messagesOnConnection[$account->id] >= $account->max_messages_per_connection) {
                $this->closeConnection($account);
            }
        } finally {
            $this->quota->releaseConnectionSlot($account);
        }
    }

    private function connectionFor(SmtpAccount $account): EsmtpTransport
    {
        if (! isset(self::$pool[$account->id])) {
            $transport = $this->transportFactory->build($account);
            $transport->start();
            self::$pool[$account->id] = $transport;
            self::$messagesOnConnection[$account->id] = 0;
        }

        return self::$pool[$account->id];
    }

    private function closeConnection(SmtpAccount $account): void
    {
        self::$pool[$account->id]->stop();
        unset(self::$pool[$account->id], self::$messagesOnConnection[$account->id]);
    }
}
