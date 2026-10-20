<?php

namespace App\Modules\Forms\Listeners;

use App\Modules\Forms\Services\FormConfirmationService;

/**
 * Listens (synchronously) to the Suppression module's
 * `DoubleOptInConfirmed` (recipientId) and confirms the contact's pending
 * form submissions. Registered in AppServiceProvider.
 */
class ConfirmFormSubmissionsOnOptIn
{
    public function __construct(private readonly FormConfirmationService $confirmations) {}

    public function handle(object $event): void
    {
        $this->confirmations->confirm((int) $event->recipientId);
    }
}
