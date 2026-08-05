<?php

namespace App\Modules\DeliveryEngine\Services;

use App\Modules\DeliveryEngine\Exceptions\SmtpConcurrencyLimitException;
use App\Modules\DeliveryEngine\Models\SmtpAccount;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Email;

/**
 * docs/17-delivery-engine.md §17.6 — SMTP Manager. A contract (rather than
 * a concrete dependency) so tests can substitute a fake transport per
 * docs/32-testing.md §32.4, without ever opening a real SMTP connection.
 */
interface SmtpManagerContract
{
    /**
     * @throws SmtpConcurrencyLimitException
     * @throws TransportExceptionInterface
     */
    public function send(SmtpAccount $account, Email $email): void;
}
