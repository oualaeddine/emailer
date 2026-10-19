<?php

namespace App\Modules\Recipients\Lists\Http\Controllers;

use App\Domain\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Modules\Recipients\Lists\Services\ListExportService;
use App\Modules\Recipients\Lists\Support\ListOperationStore;
use App\Modules\Recipients\Models\RecipientList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** docs/45 §45.5.6 — list export job, status polling and signed download. */
class ListExportController extends Controller
{
    public function __construct(
        private readonly ListExportService $exports,
        private readonly ListOperationStore $operations,
    ) {}

    public function store(Request $request, string $id): JsonResponse
    {
        Gate::authorize(PermissionName::RecipientsView->value);
        $list = RecipientList::query()->where('uuid', $id)->firstOrFail();

        return response()->json(['data' => $this->exports->queue($list, $request->user())], 202);
    }

    public function status(Request $request, string $operation): JsonResponse
    {
        $op = $this->operations->find($operation);
        abort_if($op === null, 404);
        abort_unless(
            $op['user_id'] === $request->user()->id || $request->user()->hasPermission(PermissionName::SettingsManage->value),
            403,
        );

        return response()->json(['data' => $this->exports->present($op)]);
    }

    public function download(string $operation): StreamedResponse
    {
        $op = $this->operations->find($operation);
        $path = $op['result']['path'] ?? null;
        abort_if($path === null || ! Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, basename($path), ['Content-Type' => 'text/csv']);
    }
}
