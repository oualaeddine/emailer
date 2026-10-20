<?php

namespace App\Modules\Templates\Http\Controllers;

use App\Domain\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Modules\Templates\Http\Resources\TemplateResource;
use App\Modules\Templates\Jobs\RenderThumbnailJob;
use App\Modules\Templates\Models\Template;
use App\Modules\Templates\Services\TemplateThumbnailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/** docs/45 §45.6.2 — POST /api/v1/templates/{id}/thumbnail. */
class TemplateThumbnailController extends Controller
{
    public function store(Template $template): JsonResponse
    {
        Gate::authorize(PermissionName::TemplatesManage->value);

        RenderThumbnailJob::dispatch(TemplateThumbnailService::MODEL_TEMPLATE, $template->id);

        return (new TemplateResource($template->fresh()))->response()->setStatusCode(202);
    }
}
