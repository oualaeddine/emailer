<?php

namespace App\Modules\Templates\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * docs/45-brevo-api-contracts.md §45.9 — fired after every template save
 * (create, update, duplicate). WP-62 listens to render the thumbnail.
 */
class TemplateSaved implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly int $templateId) {}
}
