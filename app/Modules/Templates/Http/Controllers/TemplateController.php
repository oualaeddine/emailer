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
use App\Modules\Templates\Services\TemplateFolderService;
use App\Modules\Templates\Services\TemplateService;
use App\Modules\Templates\Services\TemplateTestSendService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;

/**
 * docs/29-api-specification.md — /api/v1/templates.
 */
class TemplateController extends Controller
{
    private const GALLERY_READ_ONLY = 'Les modèles de la galerie sont en lecture seule ; dupliquez-les dans « Mes modèles ».';

    public function __construct(
        private readonly TemplateService $templates,
        private readonly TemplateFolderService $folders,
    ) {
    }

    private function guardNotGallery(Template $template): void
    {
        abort_if($template->is_gallery, 409, self::GALLERY_READ_ONLY);
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
            [
                ...$request->only(['editor_type', 'scope', 'design_json', 'subject']),
                'folder_id' => $this->folders->resolveId($request->input('folder_id')),
            ],
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
        $this->guardNotGallery($template);
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
        if ($request->has('folder_id')) {
            $current->update(['folder_id' => $this->folders->resolveId($request->input('folder_id'))]);
        }

        return new TemplateDetailResource($current->fresh());
    }

    public function duplicate(DuplicateTemplateRequest $request, Template $template): JsonResponse
    {
        $copy = $this->templates->duplicate($template, $request->user(), [
            ...$request->only(['name', 'scope']),
            'folder_id' => $this->folders->resolveId($request->input('folder_id')),
        ]);

        return (new TemplateDetailResource($copy))->response()->setStatusCode(201);
    }

    public function testSend(TestSendTemplateRequest $request, Template $template, TemplateTestSendService $testSend): JsonResponse
    {
        $key = "template-test-send:{$template->id}";
        if (RateLimiter::tooManyAttempts($key, 10)) {
            throw new ThrottleRequestsException('Limite de 10 envois de test par heure atteinte pour ce modèle.');
        }
        RateLimiter::hit($key, 3600);

        $result = $testSend->send($template, $request->input('emails'), $request->input('subject'));

        return response()->json(['data' => $result], 202);
    }

    public function archive(Template $template): TemplateResource
    {
        Gate::authorize(PermissionName::TemplatesManage->value);
        $this->guardNotGallery($template);

        return new TemplateResource($this->templates->archive($template));
    }

    public function unarchive(Template $template): TemplateResource
    {
        Gate::authorize(PermissionName::TemplatesManage->value);
        $this->guardNotGallery($template);

        return new TemplateResource($this->templates->unarchive($template));
    }

    /** docs/45 §45.6.2 — restore a version (`{version}` = version_number). */
    public function restoreVersion(Request $request, Template $template, int $version): TemplateDetailResource
    {
        Gate::authorize(PermissionName::TemplatesManage->value);
        $this->guardNotGallery($template);

        $target = $template->versions()->where('version_number', $version)->firstOrFail();

        return new TemplateDetailResource($this->templates->restoreVersion($template, $target, $request->user()));
    }

    public function versions(Template $template): AnonymousResourceCollection
    {
        Gate::authorize(PermissionName::TemplatesView->value);

        $versions = $template->versions()->with('createdBy')->get();
        $current = $versions->max('version_number');
        $versions->each(fn ($version) => $version->setAttribute('is_current', $version->version_number === $current)
            ->setAttribute('editor_type', $template->editor_type));

        return TemplateVersionResource::collection($versions);
    }

    public function destroy(Template $template): Response
    {
        Gate::authorize(PermissionName::TemplatesManage->value);
        $this->guardNotGallery($template);

        abort_unless($this->templates->canBeDeleted($template), 409, 'Ce modèle est utilisé et ne peut pas être supprimé ; archivez-le à la place.');

        $template->delete();

        return response()->noContent();
    }
}
