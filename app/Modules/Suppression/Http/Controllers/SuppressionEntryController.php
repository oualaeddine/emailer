<?php

namespace App\Modules\Suppression\Http\Controllers;

use App\Domain\Enums\SuppressionReason;
use App\Http\Controllers\Controller;
use App\Modules\Suppression\Http\Requests\DestroySuppressionEntryRequest;
use App\Modules\Suppression\Http\Requests\StoreSuppressionEntryRequest;
use App\Modules\Suppression\Http\Resources\SuppressionEntryResource;
use App\Modules\Suppression\Models\SuppressionEntry;
use App\Modules\Suppression\Services\SuppressionEntryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * docs/29-api-specification.md §29.8 — /api/v1/suppression-entries.
 * Mirrors `App\Modules\Recipients\Http\Controllers\RecipientController`.
 */
class SuppressionEntryController extends Controller
{
    public function __construct(private readonly SuppressionEntryService $suppressionEntries)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', SuppressionEntry::class);

        return SuppressionEntryResource::collection(
            $this->suppressionEntries->paginate($request->only(['reason', 'q'])),
        );
    }

    /**
     * docs/23-suppression-list.md §23.2 — adding an already-suppressed
     * email is a no-op upsert, not a conflict, so this returns `200` (not
     * `201`) when the entry already existed.
     */
    public function store(StoreSuppressionEntryRequest $request): JsonResponse
    {
        $entry = $this->suppressionEntries->create(
            $request->string('email')->toString(),
            SuppressionReason::from($request->string('reason')->toString()),
            $request->input('notes'),
            $request->user()?->id,
        );

        return (new SuppressionEntryResource($entry))
            ->response()
            ->setStatusCode($entry->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(DestroySuppressionEntryRequest $request, string $uuid): Response
    {
        $entry = $this->suppressionEntries->findByUuid($uuid);
        $this->authorize('delete', $entry);

        $this->suppressionEntries->remove($entry);

        return response()->noContent();
    }
}
