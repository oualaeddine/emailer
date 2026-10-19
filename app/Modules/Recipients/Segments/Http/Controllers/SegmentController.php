<?php

namespace App\Modules\Recipients\Segments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Recipients\Http\Resources\RecipientResource;
use App\Modules\Recipients\Models\Segment;
use App\Modules\Recipients\Segments\Http\Requests\CountSegmentRequest;
use App\Modules\Recipients\Segments\Http\Requests\IndexSegmentRequest;
use App\Modules\Recipients\Segments\Http\Requests\PreviewSegmentRequest;
use App\Modules\Recipients\Segments\Http\Requests\SegmentMembersRequest;
use App\Modules\Recipients\Segments\Http\Requests\StoreSegmentRequest;
use App\Modules\Recipients\Segments\Http\Requests\UpdateSegmentRequest;
use App\Modules\Recipients\Segments\Http\Resources\SegmentResource;
use App\Modules\Recipients\Segments\Services\FieldCatalog;
use App\Modules\Recipients\Segments\Services\SegmentService;
use App\Modules\Recipients\Views\Services\ContactQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * docs/45-brevo-api-contracts.md §45.5.2 — /api/v1/segments (WP-54).
 */
class SegmentController extends Controller
{
    public function __construct(private readonly SegmentService $segments) {}

    public function index(IndexSegmentRequest $request): AnonymousResourceCollection
    {
        $page = $this->segments->list($request->validated(), $request->integer('per_page', 25));
        SegmentResource::prime($page->getCollection());

        return SegmentResource::collection($page);
    }

    public function store(StoreSegmentRequest $request): JsonResponse
    {
        $segment = $this->segments->create($request->validated(), $request->user());

        return (new SegmentResource($segment))->response()->setStatusCode(201);
    }

    public function show(string $id): SegmentResource
    {
        $segment = $this->segments->findByUuid($id);
        $this->authorize('view', $segment);

        return new SegmentResource($segment);
    }

    public function update(UpdateSegmentRequest $request, string $id): SegmentResource
    {
        $segment = $this->segments->findByUuid($id);
        $this->authorize('update', $segment);

        return new SegmentResource($this->segments->update($segment, $request->validated(), $request->user()));
    }

    public function destroy(Request $request, string $id): Response
    {
        $segment = $this->segments->findByUuid($id);
        $this->authorize('delete', $segment);
        $this->segments->delete($segment, $request->user());

        return response()->noContent();
    }

    /** `POST /segments/count` — live count of an unsaved tree. */
    public function countRules(CountSegmentRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->segments->count($request->input('rules'))]);
    }

    /** `POST /segments/{id}/count` — refreshes the cached `contact_count`. */
    public function count(string $id): JsonResponse
    {
        $segment = $this->segments->findByUuid($id);
        $this->authorize('view', $segment);

        return response()->json(['data' => $this->segments->refreshCount($segment)]);
    }

    /**
     * `GET /segments/{id}/members` — `CursorPaginatedResponse<Contact>`: the
     * contacts-table rows (ContactQueryService, `segment_id` scope), honouring
     * `columns` (comma-separated ContactColumns keys).
     */
    public function members(SegmentMembersRequest $request, string $id, ContactQueryService $contacts): JsonResponse
    {
        $segment = $this->segments->findByUuid($id);
        $this->authorize('view', $segment);

        $columns = array_values(array_filter(explode(',', (string) $request->input('columns', '')), fn ($c) => $c !== ''));
        $effective = $contacts->effective(['segment_id' => $segment->uuid, 'columns' => $columns], $request->user());

        return response()->json($contacts->paginate(
            $effective,
            $request->user(),
            $request->integer('per_page', 25),
            $request->input('cursor'),
            url("/api/v1/segments/{$segment->uuid}/members"),
        ));
    }

    public function duplicate(Request $request, string $id): JsonResponse
    {
        $segment = $this->segments->findByUuid($id);
        $this->authorize('create', Segment::class);
        $validated = $request->validate(['name' => ['sometimes', 'nullable', 'string', 'max:150']]);

        $copy = $this->segments->duplicate($segment, $validated['name'] ?? null, $request->user());

        return (new SegmentResource($copy))->response()->setStatusCode(201);
    }

    /**
     * Legacy preview (doc 29 §29.4): `{count, sample}` without persisting.
     * Without `{id}` the body's `rules` is used; with `{id}` the body's
     * `rules` (if any) else the segment's own rules.
     */
    public function preview(PreviewSegmentRequest $request, ?string $id = null): array
    {
        $rules = $request->input('rules');
        if ($id !== null) {
            $segment = $this->segments->findByUuid($id);
            $rules ??= $this->segments->rulesOf($segment);
        }

        $preview = $this->segments->preview($rules);

        return [
            'count' => $preview['count'],
            'sample' => RecipientResource::collection($preview['sample'])->resolve(),
        ];
    }

    /** `GET /segments/fields` — §45.5.3 `FieldCatalog` (static fields + contact attributes). */
    public function fields(Request $request, FieldCatalog $catalog): JsonResponse
    {
        $this->authorize('viewAny', Segment::class);
        $validated = $request->validate(['category' => ['sometimes', Rule::in(['attribute', 'contact', 'list', 'engagement', 'date', 'pagejaunes'])]]);

        $data = $catalog->catalog();
        if (isset($validated['category'])) {
            $data['fields'] = array_values(array_filter($data['fields'], fn ($f) => $f['category'] === $validated['category']));
        }

        return response()->json(['data' => $data]);
    }
}
