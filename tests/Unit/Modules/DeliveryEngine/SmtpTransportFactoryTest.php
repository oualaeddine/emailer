<?php

namespace Tests\Unit\Modules\DeliveryEngine;

use App\Domain\Enums\SmtpEncryption;
use App\Modules\DeliveryEngine\Models\SmtpAccount;
use App\Modules\DeliveryEngine\Services\SmtpTransportFactory;
use Tests\TestCase;

/**
 * docs/17-delivery-engine.md §17.6 — EsmtpTransport configuration.
 */
class SmtpTransportFactoryTest extends TestCase
{
    private function createAccount(SmtpEncryption $encryption, int $port = 587): SmtpAccount
    {
        $account = new SmtpAccount();
        $account->name = 'Test Account';
        $account->provider = 'custom';
        $account->host = 'mail.pagesjaunes-dz.com';
        $account->port = $port;
        $account->encryption = $encryption->value;
        $account->username = 'test-user';
        $account->password_encrypted = 'secret';
        $account->from_email = 'no-reply@pagesjaunes-dz.com';

        return $account;
    }

    public function test_tls_encryption_uses_plain_socket_and_requires_starttls(): void
    {
        $factory = new SmtpTransportFactory();
        $account = $this->createAccount(SmtpEncryption::Tls, 587);

        $transport = $factory->build($account);

        // Plain TCP on initial connection (no ssl:// prefix on stream)
        $this->assertFalse($transport->getStream()->isTls());
        // Must enforce STARTTLS
        $this->assertTrue($transport->isTlsRequired());
        $this->assertTrue($transport->isAutoTls());
        $this->assertSame('test-user', $transport->getUsername());
        $this->assertSame('secret', $transport->getPassword());
    }

    public function test_ssl_encryption_uses_direct_ssl_socket(): void
    {
        $factory = new SmtpTransportFactory();
        $account = $this->createAccount(SmtpEncryption::Ssl, 465);

        $transport = $factory->build($account);

        // Direct SSL on initial connection (ssl:// prefix on stream)
        $this->assertTrue($transport->getStream()->isTls());
        $this->assertSame('test-user', $transport->getUsername());
        $this->assertSame('secret', $transport->getPassword());
    }

    public function test_none_encryption_disables_tls_and_auto_tls(): void
    {
        $factory = new SmtpTransportFactory();
        $account = $this->createAccount(SmtpEncryption::None, 25);

        $transport = $factory->build($account);

        $this->assertFalse($transport->getStream()->isTls());
        $this->assertFalse($transport->isTlsRequired());
        $this->assertFalse($transport->isAutoTls());
    }
}
