<?php

namespace App\Modules\Tracking\Support;

use Illuminate\Http\Request;

/**
 * Request facts an open / click is enriched from (doc 43 §43.6.5).
 */
final class TrackingContext
{
    public function __construct(
        public readonly ?string $userAgent = null,
        public readonly ?string $ip = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self($request->userAgent(), $request->ip());
    }
}
