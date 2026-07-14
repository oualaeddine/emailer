<?php

namespace App\Modules\Suppression\Services;

use App\Domain\Enums\RecipientStatus;
use App\Domain\Enums\SuppressionReason;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Suppression\Models\SuppressionEntry;

/**
 * docs/23-suppression-list.md §23.2, §23.3 — the single authoritative gate
 * consulted before every send attempt.
 */
class SuppressionManager
{
    public function isSuppressed(string $email): bool
    {
        return SuppressionEntry::query()->where('email', $email)->exists();
    }

    /**
     * Upserts a suppression entry (unique on email — a re-triggered
     * suppression for an already-suppressed address is a no-op) and marks
     * any matching recipient record suppressed.
     */
    public function suppress(
        string $email,
        SuppressionReason $reason,
        ?int $sourceMessageId = null,
        ?string $notes = null,
        ?int $addedBy = null,
    ): SuppressionEntry {
        $entry = SuppressionEntry::query()->firstOrCreate(
            ['email' => $email],
            [
                'reason' => $reason->value,
                'source_message_id' => $sourceMessageId,
                'notes' => $notes,
                'added_by' => $addedBy,
            ],
        );

        Recipient::query()->where('email', $email)->update(['status' => RecipientStatus::Suppressed->value]);

        return $entry;
    }
}
