<?php

namespace App\Modules\Suppression\Preferences\Support;

/**
 * Request-scoped hand-off (registered as a scoped singleton): a module that reacts to
 * DoubleOptInConfirmed (Forms) may name a page to show instead of the default confirmation
 * page. The URL comes from stored, validated data, never from the request.
 */
class ConfirmationRedirect
{
    private ?string $url = null;

    public function set(?string $url): void
    {
        $this->url = $url;
    }

    /** Only absolute http(s) URLs are ever returned. */
    public function url(): ?string
    {
        return $this->url !== null && preg_match('#^https?://[^\s]+$#i', $this->url) === 1 ? $this->url : null;
    }
}
