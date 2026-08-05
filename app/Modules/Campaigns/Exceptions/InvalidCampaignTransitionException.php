<?php

namespace App\Modules\Campaigns\Exceptions;

use App\Modules\Campaigns\Enums\CampaignStatus;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * docs/15-campaign-management.md §15.1 — raised whenever
 * {@see \App\Modules\Campaigns\Services\CampaignService} is asked to move a
 * campaign to a status its current status's transition list does not allow.
 */
class InvalidCampaignTransitionException extends Exception
{
    public function __construct(public readonly CampaignStatus $from, public readonly CampaignStatus $to)
    {
        parent::__construct("Cannot transition campaign from [{$from->value}] to [{$to->value}].");
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => "Impossible de faire passer la campagne de « {$this->from->value} » à « {$this->to->value} ».",
        ], 422);
    }
}
