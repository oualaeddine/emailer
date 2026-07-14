<?php

namespace App\Modules\Recipients\Http\Controllers;

use App\Domain\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Modules\Recipients\Http\Requests\StoreRecipientNoteRequest;
use App\Modules\Recipients\Http\Resources\RecipientNoteResource;
use App\Modules\Recipients\Services\RecipientService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * docs/29-api-specification.md §29.4 — /api/v1/recipients/{uuid}/notes.
 * docs/26-rbac.md §26.4 — notes gated by `recipients.annotate`.
 */
class RecipientNoteController extends Controller
{
    public function __construct(private readonly RecipientService $recipients)
    {
    }

    public function index(string $uuid): AnonymousResourceCollection
    {
        Gate::authorize(PermissionName::RecipientsView->value);

        $recipient = $this->recipients->findByUuid($uuid);

        return RecipientNoteResource::collection($recipient->notes()->with('user')->latest()->get());
    }

    public function store(StoreRecipientNoteRequest $request, string $uuid): RecipientNoteResource
    {
        Gate::authorize(PermissionName::RecipientsAnnotate->value);

        $recipient = $this->recipients->findByUuid($uuid);
        $note = $this->recipients->addNote($recipient, $request->string('body')->toString(), $request->user());

        return new RecipientNoteResource($note->load('user'));
    }
}
