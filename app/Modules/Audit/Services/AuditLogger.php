<?php

namespace App\Modules\Audit\Services;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * docs/27-audit-logs.md — the single service that writes to `audit_logs`.
 * Invoked exclusively from queued Domain Event listeners (§27.5), never
 * called directly by controllers/services, so audit coverage is fully
 * enumerable from the Event → Listener map (docs/31-events.md).
 */
class AuditLogger
{
    /**
     * Attribute names that must never be written verbatim to old_values /
     * new_values, regardless of which model they belong to (docs/27-audit-logs.md §27.4).
     *
     * @var list<string>
     */
    private const REDACTED_ATTRIBUTES = [
        'password',
        'password_encrypted',
        'remember_token',
        'token',
    ];

    public function __construct(private readonly Request $request)
    {
    }

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function record(
        string $action,
        ?Model $auditable,
        ?User $actor,
        ?array $oldValues = null,
        ?array $newValues = null,
    ): AuditLog {
        return AuditLog::query()->create([
            'user_id' => $actor?->id,
            'action' => $action,
            'auditable_type' => $auditable !== null ? $auditable::class : self::class,
            'auditable_id' => $auditable?->getKey() ?? 0,
            'old_values' => $this->redact($oldValues),
            'new_values' => $this->redact($newValues),
            'ip_address' => $this->request->ip() ?? '0.0.0.0',
            'user_agent' => $this->request->userAgent(),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $values
     * @return array<string, mixed>|null
     */
    private function redact(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        foreach (self::REDACTED_ATTRIBUTES as $attribute) {
            if (array_key_exists($attribute, $values)) {
                $values[$attribute] = '[REDACTED]';
            }
        }

        return $values;
    }
}
