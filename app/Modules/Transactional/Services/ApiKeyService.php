<?php

namespace App\Modules\Transactional\Services;

use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * docs/45 §45.7.6 — API keys are Sanctum personal access tokens (abilities =
 * scopes). No column is added to `personal_access_tokens` (§45.8 "Not in a
 * slot"): the key's uuid, label, 8-char prefix and last IP live in a JSON
 * document stored in the `name` column. The plain token is `pjm_` + 40
 * random chars (no `id|` prefix: Sanctum looks such tokens up by hash) and is
 * returned exactly once.
 */
class ApiKeyService
{
    public const KIND = 'api_key';

    public const SCOPES = [
        'transactional:send',
        'transactional:read',
        'contacts:read',
        'contacts:write',
        'campaigns:read',
        'statistics:read',
    ];

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Sanctum token extractor: `Authorization: Bearer <key>` or Brevo-style
     * `api-key: <key>` header.
     */
    public static function tokenFromRequest(Request $request): ?string
    {
        $token = $request->bearerToken() ?: $request->header('api-key');

        return is_string($token) && $token !== '' ? $token : null;
    }

    /**
     * @return Collection<int, PersonalAccessToken>
     */
    public function list(): Collection
    {
        return $this->query()->with('tokenable')->latest('id')->get();
    }

    public function findByUuid(string $uuid): PersonalAccessToken
    {
        abort_unless(Str::isUuid($uuid), 404);

        return $this->query()
            ->where('name', 'like', '%"id":"'.$uuid.'"%')
            ->with('tokenable')
            ->firstOrFail();
    }

    /**
     * @param  array{name: string, scopes: list<string>, expires_at?: ?string}  $input
     * @return array{0: PersonalAccessToken, 1: string} token + plain text key (shown once)
     */
    public function create(User $actor, array $input): array
    {
        $plain = 'pjm_'.Str::random(40);
        $meta = [
            'kind' => self::KIND,
            'id' => (string) Str::uuid(),
            'label' => $input['name'],
            'prefix' => substr($plain, 0, 8),
            'ip' => null,
        ];

        $token = new PersonalAccessToken;
        $token->forceFill([
            'tokenable_type' => $actor->getMorphClass(),
            'tokenable_id' => $actor->getKey(),
            'name' => json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'token' => hash('sha256', $plain),
            'abilities' => array_values(array_unique($input['scopes'])),
            'expires_at' => ! empty($input['expires_at']) ? Carbon::parse($input['expires_at']) : null,
        ])->save();

        $this->audit->record('api_key.created', $token, $actor, null, [
            'name' => $input['name'],
            'scopes' => $input['scopes'],
        ]);

        return [$token->load('tokenable'), $plain];
    }

    public function revoke(PersonalAccessToken $token, User $actor): void
    {
        $meta = self::meta($token);
        $this->audit->record('api_key.revoked', $token, $actor, ['name' => $meta['label'], 'scopes' => $token->abilities]);
        $token->delete();
    }

    /** Remembers the caller IP of the key (shown in the "last used" column). */
    public function touchIp(PersonalAccessToken $token, ?string $ip): void
    {
        $meta = self::meta($token);
        if ($ip === null || ($meta['ip'] ?? null) === $ip) {
            return;
        }
        $meta['ip'] = $ip;
        $token->forceFill(['name' => json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)])->save();
    }

    public static function isApiKey(PersonalAccessToken $token): bool
    {
        return (self::meta($token)['kind'] ?? null) === self::KIND;
    }

    /**
     * @return array{kind?: string, id?: string, label?: string, prefix?: string, ip?: ?string}
     */
    public static function meta(PersonalAccessToken $token): array
    {
        $decoded = json_decode((string) $token->name, true);

        return is_array($decoded) ? $decoded : ['label' => (string) $token->name];
    }

    /**
     * @return Builder<PersonalAccessToken>
     */
    private function query(): Builder
    {
        return PersonalAccessToken::query()->where('name', 'like', '{"kind":"'.self::KIND.'"%');
    }
}
