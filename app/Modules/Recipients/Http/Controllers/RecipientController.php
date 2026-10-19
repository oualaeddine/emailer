<?php

namespace App\Modules\Recipients\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Recipients\DataTransferObjects\CreateRecipientData;
use App\Modules\Recipients\Http\Requests\RecipientIndexRequest;
use App\Modules\Recipients\Http\Requests\StoreRecipientRequest;
use App\Modules\Recipients\Views\Services\ContactQueryService;
use App\Modules\Recipients\Views\Support\ContactColumns;
use App\Modules\Recipients\Http\Requests\UpdateRecipientRequest;
use App\Modules\Recipients\Http\Resources\RecipientResource;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Recipients\Services\RecipientService;
use App\Domain\Enums\RecipientSource;
use App\Modules\Recipients\Attributes\Contracts\ContactAttributeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

/**
 * docs/29-api-specification.md §29.4 — /api/v1/recipients.
 */
class RecipientController extends Controller
{
    public function __construct(
        private readonly RecipientService $recipients,
        private readonly ContactQueryService $contacts,
    ) {
    }

    /**
     * docs/45 §45.5.4 — `pagination=cursor` returns the ContactIndexResponse;
     * otherwise the legacy page-based response is unchanged.
     */
    public function index(RecipientIndexRequest $request): AnonymousResourceCollection|JsonResponse
    {
        $this->authorize('viewAny', Recipient::class);

        if ($request->input('pagination') === 'cursor') {
            return $this->cursorIndex($request);
        }

        return RecipientResource::collection(
            $this->recipients->paginate($request->only(['q', 'source', 'status', 'tag_id'])),
        );
    }

    public function search(RecipientIndexRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Recipient::class);

        return $this->cursorIndex($request);
    }

    private function cursorIndex(RecipientIndexRequest $request): JsonResponse
    {
        $effective = $this->contacts->effective($request->validated(), $request->user());

        return response()->json($this->contacts->paginate(
            $effective,
            $request->user(),
            $request->integer('per_page', 50),
            $request->input('cursor'),
            url('/api/v1/recipients'),
        ));
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

        if (ContactColumns::hasRecipientColumn('created_by')) {
            $recipient->forceFill(['created_by' => $request->user()?->id])->saveQuietly();
        }

        return (new RecipientResource($recipient->load('tags')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $uuid): RecipientResource
    {
        $recipient = $this->recipients->findByUuid($uuid);
        $this->authorize('view', $recipient);
        $recipient->loadMissing(['pageJaunesCompany', 'createdBy']);
        $recipient->load('recipientLists:recipient_lists.id,recipient_lists.uuid,recipient_lists.name');

        return new RecipientResource($recipient);
    }

    public function update(UpdateRecipientRequest $request, Recipient $recipient): RecipientResource
    {
        $this->authorize('update', $recipient);

        DB::transaction(function () use ($request, $recipient): void {
            $recipient->fill($request->safe()->only(['first_name', 'last_name', 'company_name', 'status']));

            // `custom_fields` is a partial update: sent keys are merged, null clears a key.
            if ($request->has('custom_fields')) {
                $fields = $recipient->custom_fields ?? [];
                foreach ($request->clearedCustomFields() as $key) {
                    unset($fields[$key]);
                }
                $recipient->custom_fields = array_merge($fields, (array) $request->validated('custom_fields', []));
            }
            $recipient->save();

            // Typed attribute writes (and `ext_id` → EXT_ID) go through the single writer.
            $attributes = (array) $request->validated('attributes', []);
            if ($request->exists('ext_id')) {
                $attributes['EXT_ID'] = $request->validated('ext_id');
            }
            if ($attributes !== []) {
                app(ContactAttributeService::class)->apply($recipient, $attributes, 'manual');
            }

            if ($request->has('tag_ids')) {
                $this->recipients->syncTags($recipient, $request->input('tag_ids'));
            }
        });

        return new RecipientResource($recipient->fresh(['tags']));
    }
}
