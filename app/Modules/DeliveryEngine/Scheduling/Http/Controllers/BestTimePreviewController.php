<?php

namespace App\Modules\DeliveryEngine\Scheduling\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\DeliveryEngine\Scheduling\Contracts\BestSendTimeService;
use App\Modules\DeliveryEngine\Scheduling\Http\Resources\BestTimePreviewResource;

/**
 * docs/45-brevo-api-contracts.md §45.7.3 — `GET /campaigns/{id}/best-time-preview`
 * (permission `campaigns.create`): explanation shown under the
 * *Envoyer au meilleur moment* option of *Confirmer l'envoi*.
 */
class BestTimePreviewController extends Controller
{
    public function __construct(private readonly BestSendTimeService $bestTime) {}

    public function show(Campaign $campaign): BestTimePreviewResource
    {
        $this->authorize('create', Campaign::class);

        return new BestTimePreviewResource($this->bestTime->explain($campaign));
    }
}
