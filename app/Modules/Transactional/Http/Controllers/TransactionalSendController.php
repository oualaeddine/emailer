<?php

namespace App\Modules\Transactional\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tracking\Models\Message;
use App\Modules\Transactional\Contracts\TransactionalSender;
use App\Modules\Transactional\Http\Requests\TransactionalSendRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\PersonalAccessToken;

/** docs/45 §45.7.6 — POST /api/v1/transactional/send. */
class TransactionalSendController extends Controller
{
    public function __construct(private readonly TransactionalSender $sender) {}

    public function store(TransactionalSendRequest $request): JsonResponse
    {
        $user = $request->user();
        $token = $user->currentAccessToken();
        $byKey = $token instanceof PersonalAccessToken;

        $limit = $byKey
            ? (int) config('transactional.key_rate_limit', 600)
            : (int) config('transactional.session_rate_limit', 60);
        $key = $byKey ? 'transactional-send:key:'.$token->id : 'transactional-send:user:'.$user->id;

        if (RateLimiter::tooManyAttempts($key, $limit)) {
            return response()->json(['message' => 'Trop de requêtes. Réessayez dans quelques instants.'], 429)
                ->header('Retry-After', (string) RateLimiter::availableIn($key));
        }
        RateLimiter::hit($key, 60);

        $messages = $this->sender->send($request->validated(), [
            'source' => 'api',
            'actor' => $user,
            'api_key_id' => $byKey ? $token->id : null,
        ]);

        return response()->json(['data' => [
            'message_id' => '<'.$messages[0]->uuid.'@pagesjaunes-mailer>',
            'message_ids' => array_map(fn (Message $m): string => $m->uuid, $messages),
        ]], 201);
    }
}
