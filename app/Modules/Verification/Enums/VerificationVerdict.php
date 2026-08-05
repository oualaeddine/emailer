<?php

namespace App\Modules\Verification\Enums;

/**
 * docs/04-database-design.md §4.11 — `verification_results.verdict` CHECK
 * values; docs/22-email-verification.md §22.4 — Verdict Computation.
 *
 * Module-local (not `App\Domain\Enums`, which is frozen per
 * docs/42-parallel-execution-plan.md §42.11 — "all needed cases already
 * exist"; `verdict` is a new concept this work package introduces, mirroring
 * how WP-20 added `App\Modules\Campaigns\Enums\CampaignStatus` rather than
 * touching the frozen shared enum namespace).
 */
enum VerificationVerdict: string
{
    case Deliverable = 'deliverable';
    case Risky = 'risky';
    case Undeliverable = 'undeliverable';
    case Unknown = 'unknown';

    /**
     * Maps this DB-facing verdict onto the API/UI status vocabulary already
     * present in `resources/js/Lib/i18n/fr.ts` under `verification.status*`
     * (`statusValid`/`statusInvalid`/`statusRisky`/`statusUnknown`) — the
     * frontend never sees the raw `deliverable`/`undeliverable` DB values.
     */
    public function apiStatus(): string
    {
        return match ($this) {
            self::Deliverable => 'valid',
            self::Undeliverable => 'invalid',
            self::Risky => 'risky',
            self::Unknown => 'unknown',
        };
    }
}
