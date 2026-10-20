<?php

namespace App\Modules\Campaigns\Http\Controllers;

use App\Domain\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Modules\Campaigns\Http\Requests\CampaignFolderRequest;
use App\Modules\Campaigns\Http\Resources\CampaignFolderResource;
use App\Modules\Campaigns\Models\CampaignFolder;
use App\Modules\Campaigns\Services\CampaignFolderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * docs/45-brevo-api-contracts.md §45.4.2 — `/campaign-folders` (`DataList<Folder>`).
 */
class CampaignFolderController extends Controller
{
    public function __construct(private readonly CampaignFolderService $folders) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()?->hasPermission(PermissionName::CampaignsView->value), 403);

        return CampaignFolderResource::collection($this->folders->all());
    }

    public function store(CampaignFolderRequest $request): JsonResponse
    {
        $folder = $this->folders->create($request->validated('name'), $request->user());

        return (new CampaignFolderResource($folder))->response()->setStatusCode(201);
    }

    public function update(CampaignFolderRequest $request, CampaignFolder $folder): CampaignFolderResource
    {
        return new CampaignFolderResource($this->folders->rename($folder, $request->validated('name')));
    }

    public function destroy(Request $request, CampaignFolder $folder): JsonResponse
    {
        abort_unless($request->user()?->hasPermission(PermissionName::CampaignsCreate->value), 403);

        $this->folders->delete($folder);

        return response()->json(null, 204);
    }
}
