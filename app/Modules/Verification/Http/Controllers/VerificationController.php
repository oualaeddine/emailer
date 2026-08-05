<?php

namespace App\Modules\Verification\Http\Controllers;

use App\Domain\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Modules\Recipients\Services\RecipientService;
use App\Modules\Verification\Http\Requests\VerifyBulkRequest;
use App\Modules\Verification\Http\Resources\VerificationResultResource;
use App\Modules\Verification\Jobs\BulkVerifyRecipientsJob;
use App\Modules\Verification\Services\VerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * docs/29-api-specification.md §29.4 — `/api/v1/recipients/{uuid}/verify`
 * (`recipients.verify`, synchronous). docs/22-email-verification.md §22.7 —
 * ad hoc single + bulk verify workflows.
 */
class VerificationController extends Controller
{
    public function __construct(
        private readonly RecipientService $recipients,
        private readonly VerificationService $verification,
    ) {
    }

    /**
     * docs/29-api-specification.md §29.4 — POST
     * `/api/v1/recipients/{uuid}/verify`: "synchronous, may take up to
     * provider timeout."
     */
    public function verifySingle(string $uuid): VerificationResultResource
    {
        Gate::authorize(PermissionName::RecipientsVerify->value);

        $recipient = $this->recipients->findByUuid($uuid);
        $result = $this->verification->verifyRecipient($recipient);

        return new VerificationResultResource($result);
    }

    /**
     * docs/22-email-verification.md §22.7 — ad hoc bulk-verify action;
     * queued (not synchronous) since it may cover an arbitrarily large
     * recipient list, mirroring how imports are processed asynchronously.
     */
    public function verifyBulk(VerifyBulkRequest $request): JsonResponse
    {
        $uuids = $request->validated('recipient_uuids');

        BulkVerifyRecipientsJob::dispatch($uuids);

        return response()->json(['queued' => count($uuids)], 202);
    }
}
