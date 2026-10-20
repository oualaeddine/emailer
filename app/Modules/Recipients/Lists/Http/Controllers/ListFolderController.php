<?php

namespace App\Modules\Recipients\Lists\Http\Controllers;

use App\Domain\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Modules\Recipients\Lists\Http\Requests\ListFolderRequest;
use App\Modules\Recipients\Lists\Http\Resources\ListFolderResource;
use App\Modules\Recipients\Lists\Models\ListFolder;
use App\Modules\Recipients\Lists\Services\ListFolderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/** docs/45 §45.5.6 — /api/v1/list-folders. */
class ListFolderController extends Controller
{
    public function __construct(private readonly ListFolderService $folders) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize(PermissionName::RecipientsView->value);
        $kind = $request->query('kind', 'list') === 'segment' ? 'segment' : 'list';

        return ListFolderResource::collection($this->folders->all($kind));
    }

    public function store(ListFolderRequest $request): JsonResponse
    {
        $kind = $request->input('kind', 'list');
        $this->authorizeKind($kind);
        $folder = $this->folders->create($request->string('name')->toString(), $kind, $request->user());

        return (new ListFolderResource($folder))->response()->setStatusCode(201);
    }

    public function update(ListFolderRequest $request, string $id): ListFolderResource
    {
        $folder = $this->find($id);
        $this->authorizeKind($folder->kind);

        return new ListFolderResource($this->folders->rename($folder, $request->string('name')->toString(), $request->user()));
    }

    public function destroy(Request $request, string $id): Response
    {
        $folder = $this->find($id);
        $this->authorizeKind($folder->kind);
        $this->folders->delete($folder, $request->boolean('delete_children'), $request->user());

        return response()->noContent();
    }

    private function find(string $uuid): ListFolder
    {
        return ListFolder::query()->where('uuid', $uuid)->firstOrFail();
    }

    private function authorizeKind(string $kind): void
    {
        Gate::authorize(($kind === 'segment' ? PermissionName::SegmentsManage : PermissionName::RecipientsManage)->value);
    }
}
