<?php

namespace App\Modules\Campaigns\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * docs/15-campaign-management.md §15.2/§15.4 — raised when a campaign is
 * asked to schedule/send while missing required data (no audience, no
 * content, or review-before-sending is on and `approved_by` is not set).
 */
class CampaignNotReadyException extends Exception
{
    public function render(Request $request): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 422);
    }
}
