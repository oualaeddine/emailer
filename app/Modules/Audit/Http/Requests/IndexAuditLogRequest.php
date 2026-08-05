<?php

namespace App\Modules\Audit\Http\Requests;

use App\Domain\Enums\PermissionName;
use Illuminate\Foundation\Http\FormRequest;

/**
 * docs/29-api-specification.md §29.2/§29.9 (audit row, line 177) —
 * `GET /api/v1/audit-logs`, gated by `audit.view`.
 * docs/27-audit-logs.md §27.7 — filterable by user, action, date range
 * (and, per §27.7's "affected entity type", `auditable_type` too).
 *
 * Mirrors `App\Modules\Suppression\Http\Requests\StoreSuppressionEntryRequest`'s
 * pattern of gating via `authorize()` directly on the permission rather
 * than through a Policy class, since this work package's file scope does
 * not include `app/Modules/Audit/Policies/**` (docs/42 §42.8 file list).
 */
class IndexAuditLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(PermissionName::AuditView->value) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['sometimes', 'integer'],
            'action' => ['sometimes', 'string', 'max:100'],
            'auditable_type' => ['sometimes', 'string', 'max:100'],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date', 'after_or_equal:date_from'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:200'],
        ];
    }
}
