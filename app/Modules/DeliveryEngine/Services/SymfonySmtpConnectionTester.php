<?php

namespace App\Modules\DeliveryEngine\Services;

use App\Modules\DeliveryEngine\DataTransferObjects\SmtpTestResult;
use App\Modules\DeliveryEngine\Models\SmtpAccount;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Email;

/**
 * docs/18-smtp-management.md §18.4 — real SMTP handshake/send, using
 * Symfony Mailer's transport directly (not Laravel's `mail` config,
 * since SMTP accounts are user-managed data resolved per-account at
 * runtime, per docs/17-delivery-engine.md §17.6).
 */
class SymfonySmtpConnectionTester implements SmtpConnectionTesterContract
{
    public function __construct(private readonly SmtpTransportFactory $transportFactory)
    {
    }

    public function testConnection(SmtpAccount $account): SmtpTestResult
    {
        $transport = $this->buildTransport($account);

        try {
            $transport->start();

            return new SmtpTestResult(true, "Connexion établie avec succès à {$account->host}:{$account->port}.");
        } catch (TransportExceptionInterface $e) {
            return new SmtpTestResult(false, $e->getMessage());
        } finally {
            $transport->stop();
        }
    }

    public function sendTestEmail(SmtpAccount $account, string $toEmail): SmtpTestResult
    {
        $transport = $this->buildTransport($account);

        $email = (new Email())
            ->from($account->from_email)
            ->to($toEmail)
            ->subject('E-mail de test — PageJaunes Mailer')
            ->text("Ceci est un e-mail de test envoyé depuis le compte SMTP « {$account->name} ».");

        try {
            $transport->send($email);

            return new SmtpTestResult(true, "E-mail de test envoyé avec succès à {$toEmail}.");
        } catch (TransportExceptionInterface $e) {
            return new SmtpTestResult(false, $e->getMessage());
        } finally {
            $transport->stop();
        }
    }

    private function buildTransport(SmtpAccount $account): EsmtpTransport
    {
        return $this->transportFactory->build($account);
    }
}
