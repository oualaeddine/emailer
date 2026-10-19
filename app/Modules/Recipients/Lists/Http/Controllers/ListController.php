<?php

namespace App\Modules\Recipients\Lists\Http\Controllers;

use App\Domain\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Modules\Recipients\Lists\Http\Requests\ListInputRequest;
use App\Modules\Recipients\Lists\Http\Resources\ContactListResource;
use App\Modules\Recipients\Lists\Jobs\DeleteListJob;
use App\Modules\Recipients\Lists\Services\ListService;
use App\Modules\Recipients\Views\Contracts\AsyncOperations;
use App\Modules\Recipients\Views\Http\Resources\AsyncOperationResource;
use App\Modules\Recipients\Models\RecipientList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/** docs/45 §45.5.6 — /api/v1/recipient-lists. */
class ListController extends Controller
{
    public function __construct(
        private readonly ListService $lists,
        private readonly AsyncOperations $operations,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize(PermissionName::RecipientsView->value);

        return ContactListResource::collection($this->lists->paginate($request->only(['q', 'folder_id', 'sort', 'per_page', 'include_legacy'])));
    }

    public function store(ListInputRequest $request): JsonResponse
    {
        $list = $this->lists->create($request->validated(), $request->user());

        return (new ContactListResource($list))->response()->setStatusCode(201);
    }

    public function show(string $id): ContactListResource
    {
        Gate::authorize(PermissionName::RecipientsView->value);

        return new ContactListResource($this->find($id)->load(['createdBy', 'segment']));
    }

    public function update(ListInputRequest $request, string $id): ContactListResource
    {
        return new ContactListResource($this->lists->update($this->find($id), $request->validated(), $request->user()));
    }

    public function destroy(Request $request, string $id): Response|JsonResponse
    {
        Gate::authorize(PermissionName::RecipientsManage->value);
        $list = $this->find($id);
        $this->lists->assertNotInUse($list);
        $deleteContacts = $request->boolean('delete_contacts');
        $members = $this->lists->memberCount($list);

        if ($members > ListService::ASYNC_DELETE_THRESHOLD) {
            $op = $this->operations->start('list_delete', $request->user(), [
                'list_id' => $list->id, 'delete_contacts' => $deleteContacts,
            ], $members);
            DeleteListJob::dispatch($list->id, $deleteContacts, $op->id, $request->user()->id);

            return (new AsyncOperationResource($op->fresh()))->response()->setStatusCode(202);
        }

        $this->lists->delete($list, $deleteContacts, $request->user());

        return response()->noContent();
    }

    private function find(string $uuid): RecipientList
    {
        return RecipientList::query()->where('uuid', $uuid)->firstOrFail();
    }
}
