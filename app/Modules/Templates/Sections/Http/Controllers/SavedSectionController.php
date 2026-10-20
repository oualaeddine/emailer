<?php

namespace App\Modules\Templates\Sections\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Templates\Sections\Http\Requests\StoreSavedSectionRequest;
use App\Modules\Templates\Sections\Http\Requests\UpdateSavedSectionRequest;
use App\Modules\Templates\Sections\Http\Resources\SavedSectionResource;
use App\Modules\Templates\Sections\Models\SavedSection;
use App\Modules\Templates\Sections\Services\SavedSectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/** docs/45-brevo-api-contracts.md §45.7.1 — /api/v1/saved-sections. */
class SavedSectionController extends Controller
{
    public function __construct(private readonly SavedSectionService $sections) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', SavedSection::class);

        $q = $request->query('q');

        return SavedSectionResource::collection($this->sections->list(is_string($q) ? $q : null));
    }

    public function store(StoreSavedSectionRequest $request): JsonResponse
    {
        /** @var array<string, mixed> $component */
        $component = $request->input('component');

        $section = $this->sections->create(
            $request->string('name')->toString(),
            $request->string('mjml')->toString(),
            $component,
            $request->user(),
        );

        return (new SavedSectionResource($section))->response()->setStatusCode(201);
    }

    public function update(UpdateSavedSectionRequest $request, SavedSection $section): SavedSectionResource
    {
        return new SavedSectionResource($this->sections->rename($section, $request->string('name')->toString()));
    }

    public function destroy(SavedSection $section): Response
    {
        Gate::authorize('delete', $section);

        $this->sections->delete($section);

        return response()->noContent();
    }
}
