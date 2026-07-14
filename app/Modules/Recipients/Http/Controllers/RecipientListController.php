<?php

namespace App\Modules\Recipients\Http\Controllers;

use App\Domain\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Modules\Recipients\Http\Resources\RecipientListResource;
use App\Modules\Recipients\Services\RecipientListService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * docs/13-recipient-management.md §13.7 — Lists vs. Segments.
 */
class RecipientListController extends Controller
{
    public function __construct(private readonly RecipientListService $lists)
    {
    }

    public function index(): AnonymousResourceCollection
    {
        Gate::authorize(PermissionName::RecipientsView->value);

        return RecipientListResource::collection($this->lists->all());
    }

    public function store(Request $request): RecipientListResource
    {
        Gate::authorize(PermissionName::RecipientsManage->value);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'rules' => ['sometimes', 'array'],
        ]);

        $list = isset($validated['rules'])
            ? $this->lists->createDynamicSegment(
                $validated['name'],
                $validated['description'] ?? null,
                $validated['rules'],
                $request->user(),
            )
            : $this->lists->createStatic($validated['name'], $validated['description'] ?? null, $request->user());

        return new RecipientListResource($list);
    }
}
