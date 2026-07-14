<?php

namespace App\Modules\DeliveryEngine\Exceptions;

use App\Modules\DeliveryEngine\Models\SmtpAccount;
use RuntimeException;

/**
 * docs/17-delivery-engine.md §17.6 — `max_concurrent_connections` reached
 * for this account at the moment of send; the caller should release the
 * job back to the queue rather than treat this as a delivery failure.
 */
class SmtpConcurrencyLimitException extends RuntimeException
{
    public function __construct(SmtpAccount $account)
    {
        parent::__construct("Limite de connexions simultanées atteinte pour le compte SMTP « {$account->name} ».");
    }
}
