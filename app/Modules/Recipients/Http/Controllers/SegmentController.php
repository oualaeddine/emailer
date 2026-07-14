<?php

namespace App\Modules\Recipients\Http\Controllers;

use App\Domain\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Modules\Recipients\Http\Resources\RecipientResource;
use App\Modules\Recipients\Services\SegmentEvaluationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * docs/29-api-specification.md §29.4 — POST /api/v1/segments/{uuid}/preview.
 * "returns {count, sample: [...]} without persisting".
 */
class SegmentController extends Controller
{
    public function __construct(private readonly SegmentEvaluationService $segments)
    {
    }

    public function preview(Request $request): array
    {
        Gate::authorize(PermissionName::SegmentsManage->value);

        $validated = $request->validate(['rules' => ['required', 'array']]);

        $results = $this->segments->compile($validated['rules'])->limit(2000)->get();

        return [
            'count' => $results->count(),
            'sample' => RecipientResource::collection($results->take(10))->resolve(),
        ];
    }
}
