<?php

namespace App\Modules\Senders\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Senders\Http\Requests\StoreSenderRequest;
use App\Modules\Senders\Http\Requests\UpdateSenderRequest;
use App\Modules\Senders\Http\Resources\SenderResource;
use App\Modules\Senders\Models\Sender;
use App\Modules\Senders\Services\SenderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;

/** docs/45 §45.4.1 — /api/v1/senders. */
class SenderController extends Controller
{
    public function __construct(private readonly SenderService $senders) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Sender::class);

        return SenderResource::collection($this->senders->list($request->only(['verified', 'q'])));
    }

    public function store(StoreSenderRequest $request): JsonResponse
    {
        $sender = $this->senders->create($request->validated(), $request->user());

        return (new SenderResource($sender))->response()->setStatusCode(201);
    }

    public function show(string $id): SenderResource
    {
        $sender = $this->senders->findByUuid($id);
        $this->authorize('view', $sender);

        return new SenderResource($sender);
    }

    public function update(UpdateSenderRequest $request, string $id): SenderResource
    {
        $sender = $this->senders->findByUuid($id);

        return new SenderResource($this->senders->update($sender, $request->validated()));
    }

    public function destroy(Request $request, string $id): Response
    {
        $sender = $this->senders->findByUuid($id);
        $this->authorize('delete', $sender);
        $this->senders->delete($sender, $request->user());

        return response()->noContent();
    }

    public function makeDefault(string $id): SenderResource
    {
        $sender = $this->senders->findByUuid($id);
        $this->authorize('update', $sender);

        return new SenderResource($this->senders->setDefault($sender));
    }

    public function resendVerification(string $id): JsonResponse
    {
        $sender = $this->senders->findByUuid($id);
        $this->authorize('update', $sender);

        $key = 'sender-verification:'.$sender->id;
        if (RateLimiter::tooManyAttempts($key, 3)) {
            return response()->json(['message' => 'Trop de demandes. Réessayez plus tard.'], 429);
        }
        $sender = $this->senders->resendVerification($sender);
        RateLimiter::hit($key, 3600);

        return (new SenderResource($sender))->response()->setStatusCode(202);
    }

    /** Public signed endpoint (route has the `signed` middleware, no auth). */
    public function verify(string $id): RedirectResponse
    {
        $sender = $this->senders->verifyByLink($id);

        return redirect('/settings/senders?verified='.$sender->uuid);
    }
}
