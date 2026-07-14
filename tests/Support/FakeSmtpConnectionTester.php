<?php

namespace Tests\Support;

use App\Modules\DeliveryEngine\DataTransferObjects\SmtpTestResult;
use App\Modules\DeliveryEngine\Models\SmtpAccount;
use App\Modules\DeliveryEngine\Services\SmtpConnectionTesterContract;

/**
 * docs/32-testing.md §32.4 — "a fake SMTP transport ... validates the
 * full Delivery Engine flow ... without hitting real providers."
 */
class FakeSmtpConnectionTester implements SmtpConnectionTesterContract
{
    public static bool $shouldSucceed = true;

    public function testConnection(SmtpAccount $account): SmtpTestResult
    {
        return new SmtpTestResult(
            self::$shouldSucceed,
            self::$shouldSucceed ? '250 OK (fake)' : '535 Authentication failed (fake)',
        );
    }

    public function sendTestEmail(SmtpAccount $account, string $toEmail): SmtpTestResult
    {
        return new SmtpTestResult(
            self::$shouldSucceed,
            self::$shouldSucceed ? '250 Message accepted (fake)' : '550 Rejected (fake)',
        );
    }
}
