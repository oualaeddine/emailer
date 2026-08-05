<?php

namespace App\Modules\Verification\DataTransferObjects;

use App\Modules\Verification\Enums\VerificationVerdict;

/**
 * Provider-agnostic result of a single `VerificationProviderContract::verify()`
 * call, before it is persisted to `verification_results`
 * (docs/22-email-verification.md §22.2 — check taxonomy; §22.3 — provider
 * abstraction). Mirrors
 * `App\Modules\DeliveryEngine\DataTransferObjects\SmtpTestResult`.
 */
final readonly class VerificationOutcome
{
    public function __construct(
        public VerificationVerdict $verdict,
        public bool $syntaxValid,
        public ?bool $mxValid = null,
        public ?bool $isDisposable = null,
        public ?bool $isRoleBased = null,
        public ?bool $isCatchAll = null,
        public ?string $provider = null,
        public ?array $rawResponse = null,
    ) {
    }
}
