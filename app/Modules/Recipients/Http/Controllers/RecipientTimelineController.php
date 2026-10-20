<?php

namespace App\Modules\Recipients\Http\Controllers;

use App\Domain\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Modules\Recipients\Http\Resources\RecipientTimelineEntryResource;
use App\Modules\Recipients\Services\RecipientService;
use App\Modules\Recipients\Services\RecipientTimelineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * docs/45-brevo-api-contracts.md §45.5.5 — GET /api/v1/recipients/{uuid}/timeline.
 *
 * The per-contact counters travel in `meta.counters` (RecipientResource is
 * owned by WP-57 and does not expose them).
 */
class RecipientTimelineController extends Controller
{
    public function __construct(
        private readonly RecipientService $recipients,
        private readonly RecipientTimelineService $timeline,
    ) {
    }

    public function index(Request $request, string $uuid): JsonResponse
    {
        Gate::authorize(PermissionName::RecipientsView->value);

        $validated = $request->validate([
            'cursor' => ['nullable', 'string', 'max:512'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.RecipientTimelineService::MAX_PER_PAGE],
            'types' => ['nullable', 'array'],
            'types.*' => ['string', Rule::in(RecipientTimelineService::TYPES)],
        ]);

        $recipient = $this->recipients->findByUuid($uuid);
        $perPage = (int) ($validated['per_page'] ?? RecipientTimelineService::DEFAULT_PER_PAGE);

        $page = $this->timeline->page($recipient, $validated['cursor'] ?? null, $perPage, $validated['types'] ?? []);

        $path = url("/api/v1/recipients/{$recipient->uuid}/timeline");
        $next = $page['next_cursor'];

        return response()->json([
            'data' => RecipientTimelineEntryResource::collection($page['data'])->resolve($request),
            'links' => [
                'first' => null,
                'last' => null,
                'prev' => null,
                'next' => $next !== null ? $path.'?'.http_build_query(array_filter([
                    'cursor' => $next, 'per_page' => $perPage, 'types' => $validated['types'] ?? null,
                ])) : null,
            ],
            'meta' => [
                'path' => $path,
                'per_page' => $perPage,
                'next_cursor' => $next,
                'prev_cursor' => null,
                'counters' => $this->timeline->counters($recipient),
            ],
        ]);
    }
}
