<?php

namespace App\Modules\DeliveryEngine\Services;

use App\Modules\DeliveryEngine\DataTransferObjects\SmtpTestResult;
use App\Modules\DeliveryEngine\Models\SmtpAccount;

/**
 * docs/18-smtp-management.md §18.4 — SMTP Testing.
 * Real implementation performs an actual SMTP handshake; tests bind a
 * fake implementation instead (docs/32-testing.md §32.4 — "a fake SMTP
 * transport ... validates the full Delivery Engine flow ... without
 * hitting real providers").
 */
interface SmtpConnectionTesterContract
{
    /**
     * "Verify Connection" — connection-only check (SMTP EHLO/NOOP
     * handshake), no email sent.
     */
    public function testConnection(SmtpAccount $account): SmtpTestResult;

    /**
     * "Send Test Email" — sends a real single message via this account.
     */
    public function sendTestEmail(SmtpAccount $account, string $toEmail): SmtpTestResult;
}
