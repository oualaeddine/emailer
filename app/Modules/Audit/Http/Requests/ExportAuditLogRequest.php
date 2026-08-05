<?php

namespace App\Modules\Audit\Http\Requests;

use App\Domain\Enums\PermissionName;
use Illuminate\Foundation\Http\FormRequest;

/**
 * docs/29-api-specification.md §29.2 (audit row, line 178) —
 * `POST /api/v1/audit-logs/export`, gated by `audit.export` (a distinct,
 * stricter permission than `audit.view` per docs/27-audit-logs.md §27.9).
 * Accepts the same filter set as {@see IndexAuditLogRequest} so an export
 * can mirror whatever the operator currently has filtered in the UI.
 */
class ExportAuditLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(PermissionName::AuditExport->value) ?? false;
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
        ];
    }
}
