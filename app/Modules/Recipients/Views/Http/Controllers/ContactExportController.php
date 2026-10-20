<?php

namespace App\Modules\Recipients\Views\Http\Controllers;

use App\Domain\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Modules\Recipients\Views\Http\Requests\ContactExportRequest;
use App\Modules\Recipients\Views\Http\Resources\AsyncOperationResource;
use App\Modules\Recipients\Views\Models\AsyncOperation;
use App\Modules\Recipients\Views\Services\ContactExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * docs/45 §45.5.4 — POST /recipients/export, signed download, GET /operations/{id}.
 */
class ContactExportController extends Controller
{
    public function __construct(private readonly ContactExportService $exports) {}

    public function store(ContactExportRequest $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission(PermissionName::ContactsExport->value), 403);

        $operation = $this->exports->start(
            $request->user(),
            (array) $request->input('selection'),
            (array) $request->input('columns', []),
            $request->input('format', 'csv'),
        );

        return (new AsyncOperationResource($operation))->response()->setStatusCode(202);
    }

    public function operation(Request $request, string $id): AsyncOperationResource
    {
        $operation = AsyncOperation::query()->where('uuid', $id)->firstOrFail();
        $user = $request->user();

        abort_unless(
            $operation->user_id === $user->id || $user->hasPermission(PermissionName::SettingsManage->value),
            403,
        );

        return new AsyncOperationResource($operation);
    }

    public function download(string $operation): BinaryFileResponse
    {
        $op = AsyncOperation::query()->where('uuid', $operation)->where('type', 'contacts_export')->firstOrFail();
        $path = $op->payload['path'] ?? null;

        abort_unless($op->status === AsyncOperation::COMPLETED && $path !== null
            && Storage::disk(ContactExportService::DISK)->exists($path), 404);
        abort_if(isset($op->result['expires_at']) && now()->gt($op->result['expires_at']), 410);

        $format = $op->payload['format'] ?? 'csv';

        return response()->download(
            Storage::disk(ContactExportService::DISK)->path($path),
            'contacts-'.now()->format('Y-m-d').'.'.$format,
            ['Content-Type' => $format === 'xlsx'
                ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                : 'text/csv; charset=UTF-8'],
        );
    }
}
