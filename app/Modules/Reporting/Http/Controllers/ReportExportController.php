<?php

namespace App\Modules\Reporting\Http\Controllers;

use App\Domain\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Reporting\Http\Requests\ReportExportRequest;
use App\Modules\Reporting\Http\Requests\StatisticsQueryRequest;
use App\Modules\Reporting\Services\ReportExportService;
use App\Modules\Reporting\Services\ReportQueryService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * docs/45 §45.6.5 — `GET /campaigns/{id}/report/export` and `GET /statistics/export` (CSV, `reporting.export`).
 * Streamed directly: the payload is aggregates only (bounded), so no async operation is needed.
 */
class ReportExportController extends Controller
{
    public function __construct(
        private readonly ReportExportService $exports,
        private readonly ReportQueryService $reports,
        private readonly AuditLogger $audit,
    ) {}

    public function campaign(ReportExportRequest $request, Campaign $campaign): StreamedResponse
    {
        Gate::authorize(PermissionName::ReportingExport->value);
        $this->reports->assertReportable($campaign);

        $tab = $request->string('tab')->toString();
        $sections = $this->exports->campaignSections($campaign, $tab);
        $filename = $this->exports->campaignFilename($campaign, $tab);

        $this->audit->record('report.exported', $campaign, $request->user(), null, [
            'scope' => 'campaign', 'tab' => $tab, 'filename' => $filename,
        ]);

        return $this->download($sections, $filename);
    }

    public function statistics(StatisticsQueryRequest $request): StreamedResponse
    {
        Gate::authorize(PermissionName::ReportingExport->value);

        $table = $request->input('table');
        if ($table === null) {
            throw ValidationException::withMessages(['table' => 'Le champ table est obligatoire.']);
        }

        $sections = $this->exports->statisticsSections($request->validated(), $table);
        $filename = 'statistiques-'.$table.'-'.now()->format('Ymd-His').'.csv';

        $this->audit->record('report.exported', null, $request->user(), null, [
            'scope' => 'statistics', 'table' => $table, 'filename' => $filename,
        ]);

        return $this->download($sections, $filename);
    }

    /**
     * @param  list<array{title: string, headers: list<string>, rows: list<list<mixed>>}>  $sections
     */
    private function download(array $sections, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($sections): void {
            $handle = fopen('php://output', 'w');
            $this->exports->write($handle, $sections);
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
