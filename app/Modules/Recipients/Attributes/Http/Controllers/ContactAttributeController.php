<?php

namespace App\Modules\Recipients\Attributes\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Recipients\Attributes\Http\Requests\DeleteContactAttributeRequest;
use App\Modules\Recipients\Attributes\Http\Requests\StoreContactAttributeRequest;
use App\Modules\Recipients\Attributes\Http\Requests\UpdateContactAttributeRequest;
use App\Modules\Recipients\Attributes\Http\Resources\ContactAttributeResource;
use App\Modules\Recipients\Attributes\Models\ContactAttribute;
use App\Modules\Recipients\Attributes\Policies\ContactAttributePolicy;
use App\Modules\Recipients\Attributes\Services\ContactAttributeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** docs/45 §45.5.1 — /api/v1/contact-attributes. */
class ContactAttributeController extends Controller
{
    public function __construct(
        private readonly ContactAttributeService $attributes,
        private readonly ContactAttributePolicy $policy,
    ) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($this->policy->viewAny($request->user()), 403);

        $withUsage = $request->boolean('include_usage');
        $rows = $this->attributes->definitions()->values()->map(function (ContactAttribute $a) use ($withUsage) {
            $resource = (new ContactAttributeResource($a));
            if ($withUsage) {
                $resource->additional(['usage' => [$a->name => $this->attributes->usageCount($a)]]);
            }

            return $resource->resolve();
        });

        return response()->json(['data' => $rows]);
    }

    public function mergeTags(Request $request): JsonResponse
    {
        abort_unless($this->policy->viewAny($request->user()), 403);

        return response()->json(['data' => $this->attributes->mergeTags()->values()]);
    }

    public function store(StoreContactAttributeRequest $request): JsonResponse
    {
        $attribute = $this->attributes->create($request->validated(), $request->user());

        return (new ContactAttributeResource($attribute))->response()->setStatusCode(201);
    }

    public function show(Request $request, string $id): ContactAttributeResource
    {
        abort_unless($this->policy->view($request->user()), 403);

        return (new ContactAttributeResource($this->find($id)));
    }

    public function update(UpdateContactAttributeRequest $request, string $id): ContactAttributeResource
    {
        return new ContactAttributeResource(
            $this->attributes->update($this->find($id), $request->validated()),
        );
    }

    public function destroy(DeleteContactAttributeRequest $request, string $id): Response
    {
        $this->attributes->delete($this->find($id), $request->user());

        return response()->noContent();
    }

    private function find(string $id): ContactAttribute
    {
        return ContactAttribute::query()->where('uuid', $id)->firstOrFail();
    }
}
