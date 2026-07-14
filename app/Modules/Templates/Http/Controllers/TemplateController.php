<?php

namespace App\Modules\Templates\Http\Controllers;

use App\Domain\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Modules\Templates\Http\Requests\StoreTemplateRequest;
use App\Modules\Templates\Http\Requests\UpdateTemplateRequest;
use App\Modules\Templates\Http\Resources\TemplateResource;
use App\Modules\Templates\Http\Resources\TemplateVersionResource;
use App\Modules\Templates\Models\Template;
use App\Modules\Templates\Services\TemplateService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * docs/29-api-specification.md — /api/v1/templates.
 */
class TemplateController extends Controller
{
    public function __construct(private readonly TemplateService $templates)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize(PermissionName::TemplatesView->value);

        return TemplateResource::collection(
            $this->templates->library($request->query('category'), $request->boolean('include_archived')),
        );
    }

    public function store(StoreTemplateRequest $request): TemplateResource
    {
        $template = $this->templates->create(
            $request->user(),
            $request->string('name')->toString(),
            $request->string('html_content')->toString(),
            $request->input('category'),
        );

        return new TemplateResource($template);
    }

    public function update(UpdateTemplateRequest $request, Template $template): TemplateResource
    {
        $current = $template;

        if ($request->has('html_content')) {
            $current = $this->templates->update(
                $current,
                $request->user(),
                $request->string('html_content')->toString(),
                $request->input('change_note'),
            );
        }

        if ($request->has('name') || $request->has('category')) {
            $current->update($request->only(['name', 'category']));
        }

        return new TemplateResource($current->fresh());
    }

    public function archive(Template $template): TemplateResource
    {
        Gate::authorize(PermissionName::TemplatesManage->value);

        return new TemplateResource($this->templates->archive($template));
    }

    public function versions(Template $template): AnonymousResourceCollection
    {
        Gate::authorize(PermissionName::TemplatesView->value);

        return TemplateVersionResource::collection($template->versions()->with('createdBy')->get());
    }

    public function destroy(Template $template): Response
    {
        Gate::authorize(PermissionName::TemplatesManage->value);

        abort_unless($this->templates->canBeDeleted($template), 409, 'Ce modèle est utilisé et ne peut pas être supprimé ; archivez-le à la place.');

        $template->delete();

        return response()->noContent();
    }
}
