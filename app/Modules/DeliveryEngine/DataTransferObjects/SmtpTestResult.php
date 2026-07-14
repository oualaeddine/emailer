<?php

namespace App\Modules\DeliveryEngine\DataTransferObjects;

/**
 * docs/18-smtp-management.md §18.4 — SMTP Testing: "Displays raw provider
 * response ... directly in the UI for diagnostics."
 */
final readonly class SmtpTestResult
{
    public function __construct(
        public bool $success,
        public string $rawResponse,
    ) {
    }
}
