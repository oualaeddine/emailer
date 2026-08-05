<?php

namespace App\Modules\Tracking\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tracking\Http\Resources\MessageResource;
use App\Modules\Tracking\Services\MessageService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * docs/16-email-history.md §16.1 — Email History (Global Log) /
 * docs/20-delivery-tracking.md §20.5 — Mailbox/Email History consumer.
 *
 * No dedicated MessagePolicy is registered here: policy classes must be
 * added to App\Providers\AuthServiceProvider's `$policies` array, which is
 * outside this work package's exclusive `app/Modules/Tracking/**` scope
 * (docs/42-parallel-execution-plan.md §42.6). Authorization is therefore
 * done directly via App\Modules\Identity\Models\User::hasPermission()/
 * hasAnyPermission(), mirroring the plain Gate::authorize(PermissionName)
 * style already used by
 * App\Modules\Recipients\Http\Controllers\RecipientNoteController, plus a
 * manual own-vs-all ownership check for the object-level case
 * (see MessageService::canView()).
 */
class MessageController extends Controller
{
    public function __construct(private readonly MessageService $messages)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        abort_unless($this->messages->canViewAny($user), 403);

        $messages = $this->messages->paginateForUser($user, $request->only([
            'folder', 'status', 'smtp_account_id', 'campaign_id', 'q', 'date_from', 'date_to',
        ]));

        return MessageResource::collection($messages);
    }

    public function show(Request $request, string $uuid): MessageResource
    {
        $message = $this->messages->findByUuid($uuid);

        abort_unless($this->messages->canView($request->user(), $message), 403);

        return new MessageResource($message);
    }
}
