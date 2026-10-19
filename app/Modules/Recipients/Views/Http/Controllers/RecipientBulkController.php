<?php

namespace App\Modules\Recipients\Views\Http\Controllers;

use App\Domain\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Recipients\Views\Http\Requests\ContactBulkRequest;
use App\Modules\Recipients\Views\Http\Resources\AsyncOperationResource;
use App\Modules\Recipients\Views\Services\ContactBulkService;
use Illuminate\Http\JsonResponse;

/**
 * docs/45 §45.5.4 — POST /recipients/bulk.
 */
class RecipientBulkController extends Controller
{
    public function __construct(private readonly ContactBulkService $bulk) {}

    public function __invoke(ContactBulkRequest $request): JsonResponse
    {
        $this->authorize('create', Recipient::class); // recipients.manage

        $action = $request->string('action')->toString();
        if (in_array($action, ['blocklist', 'unblocklist'], true)) {
            abort_unless($request->user()->hasPermission(PermissionName::SuppressionManage->value), 403);
        }

        $outcome = $this->bulk->execute(
            $request->user(),
            $action,
            (array) $request->input('selection'),
            (array) $request->input('params', []),
        );

        if (isset($outcome['operation'])) {
            return (new AsyncOperationResource($outcome['operation']))->response()->setStatusCode(202);
        }

        return response()->json(['data' => $outcome['result']]);
    }
}
