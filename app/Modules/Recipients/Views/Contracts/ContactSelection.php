<?php

namespace App\Modules\Recipients\Views\Contracts;

/**
 * docs/45 §45.3 ContactSelection — exactly one of `ids` (1–1000) / `all_matching`.
 *
 * `all_matching` is capped: a selection resolving to more than
 * {@see self::MAX_ALL_MATCHING} contacts is refused with 422
 * ("Sélection trop large"). A bulk/export of at most {@see self::SYNC_LIMIT}
 * contacts runs synchronously (bulk), larger ones become an AsyncOperation.
 */
final class ContactSelection
{
    public const MAX_IDS = 1000;

    public const SYNC_LIMIT = 1000;

    public const MAX_ALL_MATCHING = 100000;
}
