<?php

namespace App\Modules\Composer\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Composer\Http\Requests\SaveDraftRequest;
use App\Modules\Composer\Http\Resources\DraftResource;
use App\Modules\Composer\Http\Resources\DraftVersionResource;
use App\Modules\Composer\Models\Draft;
use App\Modules\Composer\Models\Signature;
use App\Modules\Composer\Services\DraftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * docs/29-api-specification.md §29.3 — /api/v1/drafts.
 */
class DraftController extends Controller
{
    public function __construct(private readonly DraftService $drafts)
    {
    }

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Draft::class);

        return DraftResource::collection($this->drafts->paginateForUser(request()->user()));
    }

    public function store(SaveDraftRequest $request): JsonResponse
    {
        $signatureId = $this->resolveSignatureId($request->input('signature_id'));

        $draft = $this->drafts->create($request->user(), $request->input('subject'), $request->input('html_body'));

        if ($signatureId !== null) {
            $draft->update(['signature_id' => $signatureId]);
        }

        return (new DraftResource($draft))->response()->setStatusCode(201);
    }

    public function update(SaveDraftRequest $request, Draft $draft): DraftResource
    {
        $this->authorize('update', $draft);

        $signatureId = $request->has('signature_id') ? $this->resolveSignatureId($request->input('signature_id')) : null;

        $updated = $this->drafts->autosave(
            $draft,
            $request->input('subject'),
            $request->input('html_body'),
            $signatureId,
        );

        return new DraftResource($updated);
    }

    public function saveVersion(Draft $draft): DraftVersionResource
    {
        $this->authorize('update', $draft);

        $version = $this->drafts->saveVersion($draft, request()->user());

        return new DraftVersionResource($version);
    }

    public function versions(Draft $draft): AnonymousResourceCollection
    {
        $this->authorize('view', $draft);

        return DraftVersionResource::collection($draft->versions()->with('createdBy')->get());
    }

    public function restoreVersion(Draft $draft, int $versionId): DraftResource
    {
        $this->authorize('update', $draft);

        $version = $draft->versions()->findOrFail($versionId);
        $restored = $this->drafts->restoreVersion($draft, $version, request()->user());

        return new DraftResource($restored);
    }

    private function resolveSignatureId(?string $signatureUuid): ?int
    {
        if ($signatureUuid === null) {
            return null;
        }

        return Signature::query()->where('uuid', $signatureUuid)->value('id');
    }
}
