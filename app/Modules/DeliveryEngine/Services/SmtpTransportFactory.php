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
        $tls = match (SmtpEncryption::from($account->encryption)) {
            SmtpEncryption::Tls, SmtpEncryption::Ssl => true,
            SmtpEncryption::None => false,
        };

        $transport = new EsmtpTransport($account->host, $account->port, $tls);
        $transport->setUsername($account->username);
        $transport->setPassword($account->password_encrypted);

        return $transport;
    }
}
