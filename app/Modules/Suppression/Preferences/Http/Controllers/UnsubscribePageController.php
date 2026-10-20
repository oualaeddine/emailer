<?php

namespace App\Modules\Suppression\Preferences\Http\Controllers;

use App\Domain\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Modules\Suppression\Preferences\Http\Requests\UnsubscribePageRequest;
use App\Modules\Suppression\Preferences\Http\Resources\UnsubscribePageResource;
use App\Modules\Suppression\Preferences\Models\UnsubscribePage;
use App\Modules\Suppression\Preferences\Services\PageViewBuilder;
use App\Modules\Suppression\Preferences\Services\PreferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/** docs/45 §45.6.6 — /api/v1/unsubscribe-pages. */
class UnsubscribePageController extends Controller
{
    public function __construct(private readonly PreferenceService $service, private readonly PageViewBuilder $views) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        abort_unless(
            $user->hasPermission(PermissionName::SettingsManage->value) || $user->hasPermission(PermissionName::CampaignsCreate->value),
            403,
        );

        return UnsubscribePageResource::collection($this->service->list(UnsubscribePage::class));
    }

    public function store(UnsubscribePageRequest $request): JsonResponse
    {
        $page = $this->service->createPage($request->validated(), $request->user());

        return (new UnsubscribePageResource($page))->response()->setStatusCode(201);
    }

    public function show(Request $request, string $id): UnsubscribePageResource
    {
        $this->authorizeManage($request);

        return new UnsubscribePageResource($this->find($id));
    }

    public function update(UnsubscribePageRequest $request, string $id): UnsubscribePageResource
    {
        return new UnsubscribePageResource($this->service->update($this->find($id), $request->validated(), $request->user()));
    }

    public function destroy(Request $request, string $id): Response
    {
        $this->authorizeManage($request);
        $this->service->delete($this->find($id));

        return response()->noContent();
    }

    public function makeDefault(Request $request, string $id): UnsubscribePageResource
    {
        $this->authorizeManage($request);

        return new UnsubscribePageResource($this->service->setDefault($this->find($id)));
    }

    public function preview(Request $request, string $id): Response
    {
        $this->authorizeManage($request);

        return response(view('preferences.page', $this->views->unsubscribePage($this->find($id)))->render())
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }

    private function find(string $id): UnsubscribePage
    {
        /** @var UnsubscribePage */
        return $this->service->findByUuid(UnsubscribePage::class, $id);
    }

    private function authorizeManage(Request $request): void
    {
        abort_unless($request->user()->hasPermission(PermissionName::SettingsManage->value), 403);
    }
}
