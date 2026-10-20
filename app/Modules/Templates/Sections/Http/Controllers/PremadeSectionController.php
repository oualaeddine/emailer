<?php

namespace App\Modules\Templates\Sections\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Templates\Sections\Models\SavedSection;
use App\Modules\Templates\Sections\Services\PremadeSectionCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/** docs/45-brevo-api-contracts.md §45.7.1 — `GET /editor/sections` (static pre-made row layouts). */
class PremadeSectionController extends Controller
{
    public function __invoke(PremadeSectionCatalog $catalog): JsonResponse
    {
        Gate::authorize('viewAny', SavedSection::class);

        return response()->json(['data' => $catalog->all()]);
    }
}
