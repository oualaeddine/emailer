<?php

namespace App\Modules\Recipients\Lists\Http\Controllers;

use App\Domain\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Modules\Recipients\Lists\Services\ListExportService;
use App\Modules\Recipients\Models\RecipientList;
use App\Modules\Recipients\Views\Http\Resources\AsyncOperationResource;
use App\Modules\Recipients\Views\Models\AsyncOperation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * docs/45 §45.5.6 — list export job and signed download. Status polling uses the
 * shared `GET /api/v1/operations/{id}` (AsyncOperations).
 */
class ListExportController extends Controller
{
    public function __construct(private readonly ListExportService $exports) {}

    public function store(Request $request, string $id): JsonResponse
    {
        Gate::authorize(PermissionName::RecipientsView->value);
        Gate::authorize(PermissionName::ContactsExport->value);
        $list = RecipientList::query()->where('uuid', $id)->firstOrFail();

        return (new AsyncOperationResource($this->exports->queue($list, $request->user())))
            ->response()->setStatusCode(202);
    }

    public function download(string $operation): StreamedResponse
    {
        $op = AsyncOperation::query()->where('uuid', $operation)->where('type', 'contacts_export')->firstOrFail();
        $path = $op->payload['path'] ?? null;
        abort_if($path === null || ! Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, basename($path), ['Content-Type' => 'text/csv']);
    }
}
