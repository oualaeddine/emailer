<?php

namespace App\Modules\Templates\Http\Controllers;

use App\Domain\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Modules\Templates\Http\Requests\DuplicateTemplateRequest;
use App\Modules\Templates\Http\Requests\StoreTemplateRequest;
use App\Modules\Templates\Http\Requests\TestSendTemplateRequest;
use App\Modules\Templates\Http\Requests\UpdateTemplateRequest;
use App\Modules\Templates\Http\Resources\TemplateDetailResource;
use App\Modules\Templates\Http\Resources\TemplateListResource;
use App\Modules\Templates\Http\Resources\TemplateResource;
use App\Modules\Templates\Http\Resources\TemplateVersionResource;
use App\Modules\Templates\Events\TemplateSaved;
use App\Modules\Templates\Models\Template;
use App\Modules\Templates\Services\TemplateService;
use App\Modules\Templates\Services\TemplateTestSendService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\JsonResponse;
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

    /**
     * v2 (`page` / `per_page` / any v2 filter present): paginated `TemplateV2`
     * list without `html_content`. Otherwise the legacy full list (docs/45 §45.10).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize(PermissionName::TemplatesView->value);

        $v2Keys = ['page', 'per_page', 'scope', 'folder_id', 'q', 'is_gallery', 'editor_type', 'is_archived', 'sort'];
        if ($request->hasAny($v2Keys)) {
            $filters = $request->only([...$v2Keys, 'category']);
            foreach (['is_gallery', 'is_archived'] as $flag) {
                if ($request->has($flag)) {
                    $filters[$flag] = $request->boolean($flag);
                }
            }
            $perPage = max(1, min(100, (int) $request->query('per_page', 25)));

            return TemplateListResource::collection($this->templates->search($filters, $perPage));
        }

        return TemplateResource::collection(
            $this->templates->library($request->query('category'), $request->boolean('include_archived')),
        );
    }

    public function store(StoreTemplateRequest $request): JsonResponse
    {
        $template = $this->templates->create(
            $request->user(),
            $request->string('name')->toString(),
            $request->string('html_content')->toString(),
            $request->input('category'),
            $request->only(['editor_type', 'scope', 'design_json', 'subject']),
        );

        return (new TemplateDetailResource($template))->response()->setStatusCode(201);
    }

    public function show(Template $template): TemplateDetailResource
    {
        Gate::authorize(PermissionName::TemplatesView->value);

        return new TemplateDetailResource($template);
    }

    public function update(UpdateTemplateRequest $request, Template $template): TemplateDetailResource
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

        $attributes = $request->only(['name', 'category', 'editor_type', 'scope', 'design_json', 'subject']);
        if ($attributes !== []) {
            $current->update($attributes);
            TemplateSaved::dispatch($current->id);
        }

        return new TemplateDetailResource($current->fresh());
    }

    public function duplicate(DuplicateTemplateRequest $request, Template $template): JsonResponse
    {
        $copy = $this->templates->duplicate($template, $request->user(), $request->only(['name', 'scope']));

        return (new TemplateDetailResource($copy))->response()->setStatusCode(201);
    }

    public function testSend(TestSendTemplateRequest $request, Template $template, TemplateTestSendService $testSend): JsonResponse
    {
        $result = $testSend->send($template, $request->input('emails'), $request->input('subject'));

        return response()->json(['data' => $result], 202);
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
