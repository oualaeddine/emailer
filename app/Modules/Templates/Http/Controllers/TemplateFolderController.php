<?php

namespace App\Modules\Templates\Http\Controllers;

use App\Domain\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Modules\Templates\Http\Requests\TemplateFolderRequest;
use App\Modules\Templates\Http\Resources\TemplateFolderResource;
use App\Modules\Templates\Models\TemplateFolder;
use App\Modules\Templates\Services\TemplateFolderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/** docs/45 §45.6.2 — /api/v1/template-folders. */
class TemplateFolderController extends Controller
{
    public function __construct(private readonly TemplateFolderService $folders) {}

    public function index(): AnonymousResourceCollection
    {
        Gate::authorize(PermissionName::TemplatesView->value);

        return TemplateFolderResource::collection($this->folders->all());
    }

    public function store(TemplateFolderRequest $request): JsonResponse
    {
        $folder = $this->folders->create($request->string('name')->toString(), $request->user());

        return (new TemplateFolderResource($folder))->response()->setStatusCode(201);
    }

    public function update(TemplateFolderRequest $request, string $id): TemplateFolderResource
    {
        $folder = TemplateFolder::query()->where('uuid', $id)->firstOrFail();

        return new TemplateFolderResource($this->folders->rename($folder, $request->string('name')->toString(), $request->user()));
    }

    public function destroy(Request $request, string $id): Response
    {
        Gate::authorize(PermissionName::TemplatesManage->value);
        $folder = TemplateFolder::query()->where('uuid', $id)->firstOrFail();
        $this->folders->delete($folder, $request->user());

        return response()->noContent();
    }
}
