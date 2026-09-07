<?php

namespace App\Modules\DeliveryEngine\Services;

use App\Domain\Enums\SmtpEncryption;
use App\Modules\DeliveryEngine\Models\SmtpAccount;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

/**
 * docs/17-delivery-engine.md §17.6 — builds a Symfony `EsmtpTransport` from
 * a user-managed `SmtpAccount` record, shared by the connection tester
 * (docs/18-smtp-management.md) and the real send path.
 */
class SmtpTransportFactory
{
    public function build(SmtpAccount $account): EsmtpTransport
    {
        $encryption = SmtpEncryption::from($account->encryption);

        // In Symfony Mailer:
        // - SSL (implicit TLS / SMTPS, e.g. port 465): $tls = true instructs SocketStream to use ssl://
        // - TLS (explicit STARTTLS, e.g. port 587 or 25): $tls = false on socket, then upgraded via STARTTLS
        // - None (plain, e.g. port 25): $tls = false, and autoTls disabled
        $tls = match ($encryption) {
            SmtpEncryption::Ssl => true,
            SmtpEncryption::Tls, SmtpEncryption::None => false,
        };

        $transport = new EsmtpTransport($account->host, $account->port, $tls);
        $transport->setUsername($account->username);
        $transport->setPassword($account->password_encrypted);

        if ($encryption === SmtpEncryption::Tls) {
            $transport->setRequireTls(true);
        } elseif ($encryption === SmtpEncryption::None) {
            $transport->setAutoTls(false);
        }

        return $transport;
    }
}
