<?php

namespace App\Modules\Suppression\Preferences\Http\Controllers;

use App\Domain\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Suppression\Preferences\Http\Requests\UpdateProfileFormRequest;
use App\Modules\Suppression\Preferences\Http\Resources\UpdateProfileFormResource;
use App\Modules\Suppression\Preferences\Models\UpdateProfileForm;
use App\Modules\Suppression\Preferences\Services\PageViewBuilder;
use App\Modules\Suppression\Preferences\Services\PreferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/** docs/45 §45.6.6 — /api/v1/update-profile-forms. */
class UpdateProfileFormController extends Controller
{
    public function __construct(private readonly PreferenceService $service, private readonly PageViewBuilder $views) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        abort_unless(
            $user->hasPermission(PermissionName::SettingsManage->value) || $user->hasPermission(PermissionName::CampaignsCreate->value),
            403,
        );

        return UpdateProfileFormResource::collection($this->service->list(UpdateProfileForm::class));
    }

    public function store(UpdateProfileFormRequest $request): JsonResponse
    {
        $form = $this->service->createForm($request->validated(), $request->user());

        return (new UpdateProfileFormResource($form))->response()->setStatusCode(201);
    }

    public function show(Request $request, string $id): UpdateProfileFormResource
    {
        $this->authorizeManage($request);

        return new UpdateProfileFormResource($this->find($id));
    }

    public function update(UpdateProfileFormRequest $request, string $id): UpdateProfileFormResource
    {
        return new UpdateProfileFormResource($this->service->update($this->find($id), $request->validated(), $request->user()));
    }

    public function destroy(Request $request, string $id): Response
    {
        $this->authorizeManage($request);
        $this->service->delete($this->find($id));

        return response()->noContent();
    }

    public function makeDefault(Request $request, string $id): UpdateProfileFormResource
    {
        $this->authorizeManage($request);

        return new UpdateProfileFormResource($this->service->setDefault($this->find($id)));
    }

    public function preview(Request $request, string $id): Response
    {
        $this->authorizeManage($request);
        $form = $this->find($id);
        $sample = new Recipient(['email' => 'exemple@exemple.dz']);

        return response(view('preferences.page', $this->views->preferencesPage($sample, $form, '#') + ['preview' => true])->render())
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }

    private function find(string $id): UpdateProfileForm
    {
        /** @var UpdateProfileForm */
        return $this->service->findByUuid(UpdateProfileForm::class, $id);
    }

    private function authorizeManage(Request $request): void
    {
        abort_unless($request->user()->hasPermission(PermissionName::SettingsManage->value), 403);
    }
}
