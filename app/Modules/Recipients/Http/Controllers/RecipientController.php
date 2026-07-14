<?php

namespace App\Modules\Recipients\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Recipients\DataTransferObjects\CreateRecipientData;
use App\Modules\Recipients\Http\Requests\StoreRecipientRequest;
use App\Modules\Recipients\Http\Requests\UpdateRecipientRequest;
use App\Modules\Recipients\Http\Resources\RecipientResource;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Recipients\Services\RecipientService;
use App\Domain\Enums\RecipientSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * docs/29-api-specification.md §29.4 — /api/v1/recipients.
 */
class RecipientController extends Controller
{
    public function __construct(private readonly RecipientService $recipients)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Recipient::class);

        return RecipientResource::collection(
            $this->recipients->paginate($request->only(['q', 'source', 'status', 'tag_id'])),
        );
    }

    public function store(StoreRecipientRequest $request): JsonResponse
    {
        $data = new CreateRecipientData(
            email: $request->string('email')->toString(),
            source: RecipientSource::from($request->string('source')->toString()),
            firstName: $request->input('first_name'),
            lastName: $request->input('last_name'),
            companyName: $request->input('company_name'),
            customFields: $request->input('custom_fields', []),
        );

        $recipient = $this->recipients->create($data);

        return (new RecipientResource($recipient->load('tags')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $uuid): RecipientResource
    {
        $recipient = $this->recipients->findByUuid($uuid);
        $this->authorize('view', $recipient);

        return new RecipientResource($recipient);
    }

    public function update(UpdateRecipientRequest $request, Recipient $recipient): RecipientResource
    {
        $this->authorize('update', $recipient);

        $recipient->fill($request->safe()->only(['first_name', 'last_name', 'company_name', 'status', 'custom_fields']));
        $recipient->save();

        if ($request->has('tag_ids')) {
            $this->recipients->syncTags($recipient, $request->input('tag_ids'));
        }

        return new RecipientResource($recipient->fresh(['tags']));
    }
}
