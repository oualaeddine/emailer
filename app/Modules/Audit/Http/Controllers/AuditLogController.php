<?php

namespace App\Modules\Audit\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Http\Requests\ExportAuditLogRequest;
use App\Modules\Audit\Http\Requests\IndexAuditLogRequest;
use App\Modules\Audit\Http\Resources\AuditLogResource;
use App\Modules\Audit\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * docs/29-api-specification.md §29.2 (line 177-178) —
 * `GET /api/v1/audit-logs` (`audit.view`), `POST /api/v1/audit-logs/export`
 * (`audit.export`). Mirrors `App\Modules\Identity\Http\Controllers\UserController`'s
 * shape; unlike that controller there is no dedicated Service class here —
 * this work package's file scope (docs/42-parallel-execution-plan.md §42.8)
 * is limited to `app/Modules/Audit/Http/**`, so the filter/query logic
 * lives directly on the controller instead of a new `Services/` class.
 */
class AuditLogController extends Controller
{
    /**
     * docs/29-api-specification.md §29.1 — `audit_logs` is cursor-paginated
     * (large/high-churn list), unlike the page-based `users`/`smtp_accounts`
     * admin lists.
     */
    public function index(IndexAuditLogRequest $request): AnonymousResourceCollection
    {
        $perPage = $request->integer('per_page', 25);

        $logs = $this->filteredQuery($request->validated())
            ->with('user')
            ->orderByDesc('id')
            ->cursorPaginate($perPage);

        return AuditLogResource::collection($logs);
    }

    /**
     * docs/27-audit-logs.md §27.7 — the audit log viewer's export action.
     * Streams CSV rows via `fputcsv` in fixed-size chunks (`chunkById`)
     * rather than loading the full filtered result set into memory, since
     * this table is explicitly unbounded/never pruned (§27.8).
     *
     * Note: `AuditLogger` (`app/Modules/Audit/Services/AuditLogger.php`)
     * is deliberately NOT called here to self-log this `export.audit_log`
     * action (docs/27-audit-logs.md §27.9 says the export itself should be
     * audited). Its own docblock states it is "invoked exclusively from
     * queued Domain Event listeners ... never called directly by
     * controllers/services" — calling it directly from this controller
     * would both violate that documented architecture and require a new
     * Domain Event + Listener, which is outside this work package's file
     * scope (`app/Modules/Audit/Http/**` only). Left as a follow-up for
     * whichever work package owns the event/listener wiring.
     */
    public function export(ExportAuditLogRequest $request): StreamedResponse
    {
        $query = $this->filteredQuery($request->validated())->with('user');

        $filename = 'audit-log-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'wb');

            fputcsv($handle, [
                'ID',
                'Utilisateur',
                'Action',
                "Type d'entité",
                'ID entité',
                'Anciennes valeurs',
                'Nouvelles valeurs',
                'Adresse IP',
                'Date',
            ]);

            $query->chunkById(500, function ($rows) use ($handle): void {
                foreach ($rows as $row) {
                    fputcsv($handle, [
                        $row->id,
                        $row->user?->name ?? 'Système',
                        $row->action,
                        $row->auditable_type,
                        $row->auditable_id,
                        $row->old_values !== null ? json_encode($row->old_values) : '',
                        $row->new_values !== null ? json_encode($row->new_values) : '',
                        $row->ip_address,
                        $row->created_at?->toIso8601String(),
                    ]);
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * docs/27-audit-logs.md §27.7 — filterable by user, action category,
     * date range, affected entity type.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<AuditLog>
     */
    private function filteredQuery(array $filters): Builder
    {
        return AuditLog::query()
            ->when(array_key_exists('user_id', $filters), fn (Builder $q) => $q->where('user_id', $filters['user_id']))
            ->when(array_key_exists('action', $filters), fn (Builder $q) => $q->where('action', $filters['action']))
            ->when(
                array_key_exists('auditable_type', $filters),
                fn (Builder $q) => $q->where('auditable_type', $filters['auditable_type']),
            )
            ->when(
                array_key_exists('date_from', $filters),
                fn (Builder $q) => $q->whereDate('created_at', '>=', $filters['date_from']),
            )
            ->when(
                array_key_exists('date_to', $filters),
                fn (Builder $q) => $q->whereDate('created_at', '<=', $filters['date_to']),
            );
    }
}
