<?php

namespace App\Modules\Campaigns\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * docs/45-brevo-api-contracts.md §45.1 — `409` state conflict for the v2
 * endpoints (PATCH, design, schedule, archive, delete, send-to-new-contacts…).
 * Legacy transition endpoints keep their `422`
 * ({@see InvalidCampaignTransitionException}).
 */
class CampaignStateConflictException extends Exception
{
    public function render(Request $request): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 409);
    }
}
