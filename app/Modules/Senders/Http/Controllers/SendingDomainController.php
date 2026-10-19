<?php

namespace App\Modules\Senders\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Senders\Http\Requests\StoreSendingDomainRequest;
use App\Modules\Senders\Http\Resources\SendingDomainResource;
use App\Modules\Senders\Models\SendingDomain;
use App\Modules\Senders\Services\DomainAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/** docs/45 §45.4.1 — /api/v1/sending-domains. */
class SendingDomainController extends Controller
{
    public function __construct(private readonly DomainAuthService $domains) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', SendingDomain::class);

        return SendingDomainResource::collection($this->domains->all());
    }

    public function store(StoreSendingDomainRequest $request): JsonResponse
    {
        $domain = $this->domains->create($request->validated('domain'), $request->user());

        return (new SendingDomainResource($domain))->response()->setStatusCode(201);
    }

    public function show(string $id): SendingDomainResource
    {
        $domain = $this->domains->findByUuid($id);
        $this->authorize('view', $domain);

        return new SendingDomainResource($domain);
    }

    public function verify(string $id): JsonResponse
    {
        $domain = $this->domains->findByUuid($id);
        $this->authorize('update', $domain);

        return (new SendingDomainResource($this->domains->requestVerification($domain)))
            ->response()->setStatusCode(202);
    }

    public function destroy(Request $request, string $id): Response
    {
        $domain = $this->domains->findByUuid($id);
        $this->authorize('delete', $domain);
        $this->domains->delete($domain, $request->user());

        return response()->noContent();
    }
}
