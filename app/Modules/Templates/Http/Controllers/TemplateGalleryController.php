<?php

namespace App\Modules\Templates\Http\Controllers;

use App\Domain\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Modules\Templates\Http\Resources\TemplateListResource;
use App\Modules\Templates\Services\TemplateGalleryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** docs/45 §45.6.2 — GET /api/v1/templates/gallery. */
class TemplateGalleryController extends Controller
{
    public function __construct(private readonly TemplateGalleryService $gallery) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user !== null
            && ($user->hasPermission(PermissionName::TemplatesView->value) || $user->hasPermission(PermissionName::CampaignsCreate->value)),
            403,
        );

        $category = $request->query('category');
        $templates = $this->gallery->list(is_string($category) && $category !== '' ? $category : null);

        return response()->json([
            'data' => TemplateListResource::collection($templates)->resolve($request),
            'meta' => ['categories' => $this->gallery->categoryCounts()],
        ]);
    }
}
