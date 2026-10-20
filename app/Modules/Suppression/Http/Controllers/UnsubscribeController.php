<?php

namespace App\Modules\Suppression\Http\Controllers;

use App\Domain\Enums\SuppressionReason;
use App\Http\Controllers\Controller;
use App\Modules\Suppression\Services\SuppressionManager;
use App\Modules\Suppression\Support\UnsubscribeUrlGenerator;
use App\Modules\Suppression\Preferences\Models\UnsubscribePage;
use App\Modules\Suppression\Preferences\Services\PageDefaults;
use App\Modules\Suppression\Preferences\Services\PageViewBuilder;
use App\Modules\Suppression\Preferences\Services\PreferenceService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
    public function __construct(
        private readonly SuppressionManager $suppressionManager,
        private readonly PageViewBuilder $views,
        private readonly PreferenceService $preferences,
    ) {
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
    public function show(Request $request, string $token): JsonResponse|View
    {
        $email = $this->decodeOrAbort($token);

        if (! $request->expectsJson()) {
            $page = $this->pageFor($request);

            return view('preferences.page', [
                ...$this->views->unsubscribePage($page),
                'action' => $request->fullUrl(),
                'email' => $email,
                'public' => true,
            ]);
        }

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
    public function confirm(Request $request, string $token): JsonResponse|View
    {
        $email = $this->decodeOrAbort($token);
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
            'reason_other' => ['nullable', 'string', 'max:500'],
        ]);

        $reason = $request->boolean('global')
            ? SuppressionReason::GlobalUnsubscribe
            : SuppressionReason::ManualUnsubscribe;

        $this->suppressionManager->suppress($email, $reason);
        $this->recordFeedback($request, $email, $data['reason'] ?? null, $data['reason_other'] ?? null);

        if (! $request->expectsJson()) {
            $content = array_merge(PageDefaults::unsubscribeContent(), $this->pageFor($request)?->content ?? []);

            return view('preferences.page', $this->views->simple(
                'confirmed',
                (string) $content['confirmation_title'],
                (string) $content['confirmation_message'],
            ));
        }

        return response()->json([
            'message' => 'Vous avez été désabonné avec succès.',
            'data' => [
                'email' => $email,
                'reason' => $reason->value,
            ],
        ]);
    }

    /** The campaign's unsubscribe page (signed `?campaign=` uuid) or the default one. */
    private function pageFor(Request $request): ?UnsubscribePage
    {
        $campaign = $request->query('campaign');
        if (is_string($campaign) && $campaign !== '') {
            $pageId = DB::table('campaigns')->where('uuid', $campaign)->value('unsubscribe_page_id');
            $page = $pageId ? UnsubscribePage::query()->find($pageId) : null;
            if ($page !== null) {
                return $page;
            }
        }

        return $this->preferences->defaultPage();
    }

    /** Survey answer -> `unsubscribe_feedback.reason` ("reason: free text" when `reason_other` is given). */
    private function recordFeedback(Request $request, string $email, ?string $reason, ?string $other): void
    {
        $reason = trim((string) $reason);
        $other = trim((string) $other);
        if ($reason === '' && $other === '') {
            return;
        }
        $text = $other === '' ? $reason : ($reason === '' ? $other : $reason.': '.$other);

        $campaign = $request->query('campaign');
        DB::table('unsubscribe_feedback')->insert([
            'recipient_id' => DB::table('recipients')->where('email', $email)->value('id'),
            'campaign_id' => is_string($campaign) && $campaign !== '' ? DB::table('campaigns')->where('uuid', $campaign)->value('id') : null,
            'reason' => mb_substr($text, 0, 255),
            'created_at' => now(),
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
