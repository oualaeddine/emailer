<?php

namespace App\Modules\Campaigns\AbTesting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Campaigns\AbTesting\Http\Requests\AbTestRequest;
use App\Modules\Campaigns\AbTesting\Http\Requests\PickAbWinnerRequest;
use App\Modules\Campaigns\AbTesting\Services\AbTestService;
use App\Modules\Campaigns\Http\Resources\CampaignDetailResource;
use App\Modules\Campaigns\Models\AbTestVariant;
use App\Modules\Campaigns\Models\Campaign;
use Illuminate\Validation\ValidationException;

/**
 * docs/45-brevo-api-contracts.md §45.7.2 (WP-80) — A/B test endpoints.
 */
class AbTestController extends Controller
{
    private const RELATIONS = ['template', 'recipientList', 'segment', 'folder', 'createdBy', 'approvedBy', 'clonedFrom', 'parentCampaign', 'singleSmtpAccount'];

    public function __construct(private readonly AbTestService $abTests) {}

    /**
     * `PUT /campaigns/{id}/ab-test` — `AbTestInput` → `CampaignV2Detail`
     * (`campaigns.create`; 409 unless draft).
     */
    public function update(AbTestRequest $request, Campaign $campaign): CampaignDetailResource
    {
        $this->authorize('prepare', $campaign);

        $data = $request->validated();

        $updated = $this->abTests->save(
            $campaign,
            (bool) $data['enabled'],
            $data['config'] ?? null,
            array_values((array) ($data['variants'] ?? [])),
        );

        return new CampaignDetailResource($updated->load(self::RELATIONS));
    }

    /**
     * `POST /campaigns/{id}/ab-test/winner` — manual pick
     * (`campaigns.send` own | `campaigns.send_any`; 409 unless the test phase is running).
     */
    public function pickWinner(PickAbWinnerRequest $request, Campaign $campaign): CampaignDetailResource
    {
        $this->authorize('send', $campaign);

        $variant = AbTestVariant::query()
            ->where('campaign_id', $campaign->id)
            ->where('uuid', $request->validated('variant_id'))
            ->first();

        if ($variant === null) {
            throw ValidationException::withMessages(['variant_id' => ['Cette version n’appartient pas à la campagne.']]);
        }

        $updated = $this->abTests->pickWinner($campaign, $variant, AbTestService::PICKED_MANUAL, $request->user());

        return new CampaignDetailResource($updated->load(self::RELATIONS));
    }
}
