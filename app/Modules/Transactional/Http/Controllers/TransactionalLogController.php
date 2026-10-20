<?php

namespace App\Modules\Transactional\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Transactional\Http\Resources\TransactionalLogResource;
use App\Modules\Transactional\Services\TransactionalLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/** docs/45 §45.7.6 — logs + settings (scope / permission checked by RequireApiScope). */
class TransactionalLogController extends Controller
{
    public function __construct(private readonly TransactionalLogService $logs) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $f = $request->validate([
            'event' => ['nullable', 'string', Rule::in(array_keys(TransactionalLogService::EVENT_TYPES))],
            'q' => ['nullable', 'string', 'max:255'],
            'tag' => ['nullable', 'string', 'max:100'],
            'template_id' => ['nullable', 'uuid'],
            'source' => ['nullable', Rule::in(['composer', 'api', 'automation', 'form'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return TransactionalLogResource::collection($this->logs->paginate($f, (int) ($f['per_page'] ?? 50)));
    }

    public function show(string $id): JsonResponse
    {
        $resource = (new TransactionalLogResource($this->logs->findByUuid($id)))->withEvents();

        return response()->json(['data' => $resource->resolve()]);
    }

    public function settings(): JsonResponse
    {
        return response()->json(['data' => [
            'api_base_url' => rtrim((string) config('app.url'), '/').'/api/v1',
            'rate_limit_per_minute' => (int) config('transactional.key_rate_limit', 600),
            'smtp_relay' => ['enabled' => false, 'host' => null, 'port' => null, 'login' => null],
        ]]);
    }
}
