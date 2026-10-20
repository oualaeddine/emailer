<?php

namespace App\Modules\Transactional\Http\Controllers;

use App\Domain\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Modules\Transactional\Http\Requests\StoreApiKeyRequest;
use App\Modules\Transactional\Http\Resources\ApiKeyResource;
use App\Modules\Transactional\Services\ApiKeyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Sanctum\PersonalAccessToken;

/** docs/45 §45.7.6 — /api/v1/api-keys (session only, `api_keys.manage`). */
class ApiKeyController extends Controller
{
    public function __construct(private readonly ApiKeyService $keys) {}

    public function index(Request $request): JsonResponse
    {
        $this->guard($request);

        return response()->json(['data' => ApiKeyResource::collection($this->keys->list())->resolve($request)]);
    }

    public function store(StoreApiKeyRequest $request): JsonResponse
    {
        $this->guard($request);
        [$token, $plain] = $this->keys->create($request->user(), $request->validated());

        return response()->json([
            'data' => [
                'api_key' => (new ApiKeyResource($token))->resolve($request),
                'plain_text_token' => $plain,
            ],
        ], 201);
    }

    public function destroy(Request $request, string $id): Response
    {
        $this->guard($request);
        $this->keys->revoke($this->keys->findByUuid($id), $request->user());

        return response()->noContent();
    }

    /** Keys cannot manage keys: session users with `api_keys.manage` only. */
    private function guard(Request $request): void
    {
        abort_if($request->user()?->currentAccessToken() instanceof PersonalAccessToken, 403, 'Les clés API ne peuvent pas gérer les clés API.');
        abort_unless($request->user()?->hasPermission(PermissionName::ApiKeysManage->value), 403);
    }
}
