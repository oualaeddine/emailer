<?php

namespace App\Modules\Suppression\Listeners;

use App\Domain\Enums\RecipientStatus;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Suppression\Models\SuppressionEntry;

/**
 * docs/45 §45.5.8 / §45.8 slot I — keeps `recipients.blocklisted_at` in sync
 * with `suppression_entries`: set when an entry is created for the email,
 * cleared when the last entry for that email is removed (reactivation).
 * Listens to Eloquent model events so every writer (SuppressionManager,
 * the CRUD endpoint, bounce/complaint handlers) is covered unchanged.
 */
class SyncRecipientBlocklist
{
    public function handleCreated(SuppressionEntry $entry): void
    {
        $recipients = Recipient::withTrashed()->where('email', $entry->email)->whereNull('blocklisted_at')->get();

        foreach ($recipients as $recipient) {
            $recipient->forceFill(['blocklisted_at' => $entry->created_at ?? now()])->saveQuietly();
            RecipientBlocklistChanged::dispatch($recipient->id, true, $entry->reason);
        }
    }

    public function handleDeleted(SuppressionEntry $entry): void
    {
        if (SuppressionEntry::query()->where('email', $entry->email)->exists()) {
            return;
        }

        $recipients = Recipient::withTrashed()->where('email', $entry->email)->whereNotNull('blocklisted_at')->get();

        foreach ($recipients as $recipient) {
            $recipient->forceFill(['blocklisted_at' => null]);
            if ($recipient->status === RecipientStatus::Suppressed->value || $recipient->status === RecipientStatus::Suppressed) {
                $recipient->status = RecipientStatus::Active->value;
            }
            $recipient->saveQuietly();
            RecipientBlocklistChanged::dispatch($recipient->id, false, $entry->reason);
        }
    }
}
