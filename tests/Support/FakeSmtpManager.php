<?php

namespace Tests\Support;

use App\Modules\DeliveryEngine\Exceptions\SmtpConcurrencyLimitException;
use App\Modules\DeliveryEngine\Models\SmtpAccount;
use App\Modules\DeliveryEngine\Services\SmtpManagerContract;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mime\Email;

/**
 * docs/32-testing.md §32.4 — fake SMTP transport for the Delivery Engine's
 * send path, so `SendEmailJob` can be exercised end-to-end without ever
 * opening a real SMTP connection.
 */
class FakeSmtpManager implements SmtpManagerContract
{
    /** @var list<array{account_id: int, to: string, subject: string}> */
    public static array $sent = [];

    public static bool $shouldSucceed = true;

    public static ?string $failureMessage = 'Connection could not be established with host (fake).';

    public static bool $throwConcurrencyLimit = false;

    public function send(SmtpAccount $account, Email $email): void
    {
        if (self::$throwConcurrencyLimit) {
            self::$throwConcurrencyLimit = false;
            throw new SmtpConcurrencyLimitException($account);
        }

        if (! self::$shouldSucceed) {
            throw new TransportException(self::$failureMessage ?? 'Fake failure.');
        }

        self::$sent[] = [
            'account_id' => $account->id,
            // `Address` has no `__toString()` — `getAddress()` is the plain
            // email string accessor.
            'to' => $email->getTo()[0]->getAddress(),
            'subject' => (string) $email->getSubject(),
        ];
    }

    public static function reset(): void
    {
        self::$sent = [];
        self::$shouldSucceed = true;
        self::$failureMessage = 'Connection could not be established with host (fake).';
        self::$throwConcurrencyLimit = false;
    }
}
