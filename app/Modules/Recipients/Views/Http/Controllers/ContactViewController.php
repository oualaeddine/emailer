<?php

namespace App\Modules\Recipients\Views\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Recipients\Views\Http\Requests\ContactViewRequest;
use App\Modules\Recipients\Views\Http\Resources\ContactViewResource;
use App\Modules\Recipients\Views\Policies\ContactViewPolicy;
use App\Modules\Recipients\Views\Services\ContactViewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * docs/45 §45.5.4 — /contact-views.
 */
class ContactViewController extends Controller
{
    public function __construct(
        private readonly ContactViewService $views,
        private readonly ContactViewPolicy $policy,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Recipient::class);

        $data = collect(ContactViewService::SYSTEM_IDS)->map(fn (string $id) => $this->views->systemView($id))
            ->concat($this->views->visibleTo($request->user())->map(fn ($v) => (new ContactViewResource($v))->resolve()))
            ->values();

        return response()->json(['data' => $data]);
    }

    public function store(ContactViewRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Recipient::class);

        $view = $this->views->create($request->user(), $request->validated());

        return (new ContactViewResource($view))->response()->setStatusCode(201);
    }

    public function update(ContactViewRequest $request, string $id): ContactViewResource
    {
        $this->authorize('viewAny', Recipient::class);
        $view = $this->views->find($id, $request->user());
        abort_unless($this->policy->manage($request->user(), $view), 403);

        return new ContactViewResource($this->views->update($view, $request->validated()));
    }

    public function destroy(Request $request, string $id): Response
    {
        $this->authorize('viewAny', Recipient::class);
        $view = $this->views->find($id, $request->user());
        abort_unless($this->policy->manage($request->user(), $view), 403);
        $view->delete();

        return response()->noContent();
    }
}
