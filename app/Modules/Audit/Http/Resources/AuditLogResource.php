<?php

namespace App\Modules\Audit\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * docs/27-audit-logs.md §27.3 — captured fields.
 *
 * Unlike most other resources in this codebase (e.g. `UserResource`,
 * `SuppressionEntryResource`), this exposes the raw internal `id` rather
 * than a `uuid`: `audit_logs` (docs/04-database-design.md §4.14 /
 * `database/migrations/..._create_audit_logs_table.php`) has no `uuid`
 * column — the table is append-only, never looked up or mutated by a
 * client-supplied identifier (no `PATCH`/`DELETE` routes exist or will
 * exist per §27.8), so the enumerable-ID concern a `uuid` guards against
 * for mutable/lookup-by-id resources doesn't apply here. Adding a `uuid`
 * column purely to satisfy the general convention for a read-only,
 * admin-only, list-and-export resource was judged not worth the migration
 * for this work package.
 *
 * @mixin \App\Modules\Audit\Models\AuditLog
 */
class AuditLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => $this->whenLoaded('user', fn () => $this->user?->name),
            'action' => $this->action,
            'auditable_type' => $this->auditable_type,
            'auditable_id' => $this->auditable_id,
            'old_values' => $this->old_values,
            'new_values' => $this->new_values,
            'ip_address' => $this->ip_address,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
