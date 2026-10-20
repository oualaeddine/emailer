<?php

namespace App\Modules\Forms\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Forms\Http\Requests\FormRequest;
use App\Modules\Forms\Http\Resources\FormResource;
use App\Modules\Forms\Http\Resources\FormSubmissionResource;
use App\Modules\Forms\Models\Form;
use App\Modules\Forms\Models\FormSubmission;
use App\Modules\Forms\Services\FormService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/** docs/45 §45.7.4 — /api/v1/forms. */
class FormController extends Controller
{
    public function __construct(private readonly FormService $forms) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Form::class);
        $filters = $request->validate([
            'q' => ['sometimes', 'nullable', 'string', 'max:150'],
            'status' => ['sometimes', 'nullable', Rule::in([Form::DRAFT, Form::ACTIVE, Form::INACTIVE])],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ]);

        return FormResource::collection($this->forms->paginate($filters, (int) ($filters['per_page'] ?? 25)));
    }

    public function store(FormRequest $request): JsonResponse
    {
        $form = $this->forms->create($request->validated(), $request->user());

        return (new FormResource($form))->response()->setStatusCode(201);
    }

    public function show(string $id): FormResource
    {
        $this->authorize('view', Form::class);

        return new FormResource($this->forms->find($id));
    }

    public function update(FormRequest $request, string $id): FormResource
    {
        return new FormResource($this->forms->update($this->forms->find($id), $request->validated(), $request->user()));
    }

    public function destroy(string $id, Request $request): Response
    {
        $this->authorize('manage', Form::class);
        $this->forms->delete($this->forms->find($id), $request->user());

        return response()->noContent();
    }

    public function duplicate(string $id, Request $request): JsonResponse
    {
        $this->authorize('manage', Form::class);

        return (new FormResource($this->forms->duplicate($this->forms->find($id), $request->user())))->response()->setStatusCode(201);
    }

    public function publish(string $id, Request $request): FormResource
    {
        $this->authorize('manage', Form::class);

        return new FormResource($this->forms->publish($this->forms->find($id), $request->user()));
    }

    public function unpublish(string $id, Request $request): FormResource
    {
        $this->authorize('manage', Form::class);

        return new FormResource($this->forms->unpublish($this->forms->find($id), $request->user()));
    }

    public function submissions(string $id, Request $request): AnonymousResourceCollection
    {
        $this->authorize('manage', Form::class);
        $form = $this->forms->find($id);
        $data = $request->validate([
            'status' => ['sometimes', 'nullable', Rule::in(FormSubmission::STATUSES)],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ]);

        $query = $form->submissions()->orderByDesc('id');
        if (! empty($data['status'])) {
            $query->where('status', $data['status']);
        }

        return FormSubmissionResource::collection($query->cursorPaginate((int) ($data['per_page'] ?? 25)));
    }
}
