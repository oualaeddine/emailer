<?php

namespace App\Modules\Verification\Services;

use App\Domain\Enums\RecipientStatus;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Verification\Enums\VerificationVerdict;
use App\Modules\Verification\Models\VerificationResult;
use Illuminate\Support\Carbon;

/**
 * docs/22-email-verification.md — Verification Module service layer.
 * §22.5 — checks for a non-expired cached `verification_results` row before
 * calling the provider (saves cost, avoids re-querying providers
 * excessively for the same address across multiple imports/recipients);
 * §22.4/§22.5 — sets `recipients.status = invalid` when a fresh check comes
 * back `undeliverable`.
 */
class VerificationService
{
    /**
     * docs/22-email-verification.md §22.5 — "default 90-day validity,
     * configurable." No Settings integration exists yet for the
     * "configurable" part (out of this work package's scope), so the
     * documented default is hardcoded here.
     */
    private const CACHE_TTL_DAYS = 90;

    public function __construct(private readonly VerificationProviderContract $provider)
    {
    }

    /**
     * docs/22-email-verification.md §22.7 — "Ad hoc: 'Verify Email' button
     * on Recipient profile → synchronous call (with loading state) →
     * immediate result."
     */
    public function verifyRecipient(Recipient $recipient): VerificationResult
    {
        $result = $this->cachedResultFor($recipient->email) ?? $this->runAndPersist($recipient->email);

        $this->applyToRecipient($recipient, $result);

        return $result;
    }

    /**
     * docs/22-email-verification.md §22.7 — ad hoc bulk-verify action from
     * the Recipients list (queued via
     * {@see \App\Modules\Verification\Jobs\BulkVerifyRecipientsJob}), same
     * per-recipient logic as the "Bulk at import" workflow would use.
     *
     * @param  list<string>  $recipientUuids
     * @return int number of recipients verified
     */
    public function verifyBulk(array $recipientUuids): int
    {
        $recipients = Recipient::query()->whereIn('uuid', $recipientUuids)->get();

        foreach ($recipients as $recipient) {
            $this->verifyRecipient($recipient);
        }

        return $recipients->count();
    }

    private function cachedResultFor(string $email): ?VerificationResult
    {
        return VerificationResult::query()
            ->where('email', $email)
            ->where('expires_at', '>', now())
            ->orderByDesc('checked_at')
            ->first();
    }

    private function runAndPersist(string $email): VerificationResult
    {
        $outcome = $this->provider->verify($email);
        $checkedAt = Carbon::now();

        return VerificationResult::query()->create([
            'email' => $email,
            'syntax_valid' => $outcome->syntaxValid,
            'mx_valid' => $outcome->mxValid,
            'is_disposable' => $outcome->isDisposable,
            'is_role_based' => $outcome->isRoleBased,
            'is_catch_all' => $outcome->isCatchAll,
            'verdict' => $outcome->verdict->value,
            'provider' => $outcome->provider,
            'raw_response' => $outcome->rawResponse,
            'checked_at' => $checkedAt,
            'expires_at' => $checkedAt->copy()->addDays(self::CACHE_TTL_DAYS),
        ]);
    }

    /**
     * docs/22-email-verification.md §22.5 — "`recipients.status` is updated
     * to `invalid` when a fresh verification returns `undeliverable`,
     * feeding directly into segment exclusion logic." Other verdicts
     * (`risky`/`unknown`/`deliverable`) deliberately leave `status`
     * untouched — doc 22 only documents the `undeliverable` → `invalid`
     * transition, and an already-`suppressed` recipient must not be
     * silently reactivated to `active` by a later `deliverable` result.
     */
    private function applyToRecipient(Recipient $recipient, VerificationResult $result): void
    {
        if ($result->verdict === VerificationVerdict::Undeliverable->value
            && $recipient->status !== RecipientStatus::Invalid->value) {
            $recipient->update(['status' => RecipientStatus::Invalid->value]);
        }
    }
}
