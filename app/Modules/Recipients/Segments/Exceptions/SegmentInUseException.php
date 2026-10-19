<?php

namespace App\Modules\Recipients\Segments\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * docs/45 §45.5.2 — `DELETE /segments/{id}` → 409 while a scheduled/running
 * campaign (or an active automation) uses the segment.
 */
class SegmentInUseException extends Exception
{
    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 409);
    }
}
