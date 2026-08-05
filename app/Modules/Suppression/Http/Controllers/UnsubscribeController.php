<?php

namespace App\Modules\Suppression\Http\Controllers;

use App\Domain\Enums\SuppressionReason;
use App\Http\Controllers\Controller;
use App\Modules\Suppression\Services\SuppressionManager;
use App\Modules\Suppression\Support\UnsubscribeUrlGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * docs/23-suppression-list.md §23.4 — public, unauthenticated unsubscribe
 * confirmation page + action. docs/29-api-specification.md §29.8 —
 * `GET/POST /unsubscribe/{token}`, no auth.
 *
 * Both routes carry the `signed` middleware (see `routes/web.php`), which
 * rejects a tampered or expired link with a `403` before either action
 * below ever runs.
 */
class UnsubscribeController extends Controller
{
    public function __construct(private readonly SuppressionManager $suppressionManager)
    {
    }

    /**
     * docs/23-suppression-list.md §23.4 point 2 — "a public, unauthenticated
     * confirmation page ... showing which list(s)/organization the
     * recipient is unsubscribing from, with a single-click confirm ... and
     * an optional 'unsubscribe from everything' checkbox". No Inertia page
     * exists yet for this public link (explicitly out of this work
     * package's scope), so this returns the data such a page would render;
     * list/organization display depends on Campaigns linkage not yet built
     * and is deferred to whichever work package adds that page.
     */
    public function show(Request $request, string $token): JsonResponse
    {
        $email = $this->decodeOrAbort($token);

        return response()->json([
            'data' => [
                'email' => $email,
                'already_suppressed' => $this->suppressionManager->isSuppressed($email),
            ],
        ]);
    }

    /**
     * docs/23-suppression-list.md §23.4 point 3 — confirmation creates a
     * suppression entry with reason `manual_unsubscribe`, or
     * `global_unsubscribe` if the "unsubscribe from everything" option was
     * chosen (`global` body param). Also the target of the one-click
     * `List-Unsubscribe-Post` header (point 4), which POSTs here with no
     * body — defaulting to `manual_unsubscribe` for that case.
     */
    public function confirm(Request $request, string $token): JsonResponse
    {
        $email = $this->decodeOrAbort($token);

        $reason = $request->boolean('global')
            ? SuppressionReason::GlobalUnsubscribe
            : SuppressionReason::ManualUnsubscribe;

        $this->suppressionManager->suppress($email, $reason);

        return response()->json([
            'message' => 'Vous avez été désabonné avec succès.',
            'data' => [
                'email' => $email,
                'reason' => $reason->value,
            ],
        ]);
    }

    private function decodeOrAbort(string $token): string
    {
        try {
            return UnsubscribeUrlGenerator::decode($token);
        } catch (InvalidArgumentException) {
            throw new NotFoundHttpException('Invalid unsubscribe link.');
        }
    }
}
