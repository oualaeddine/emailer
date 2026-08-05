<?php

namespace App\Modules\Reporting\Http\Controllers;

use App\Domain\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\DeliveryEngine\Models\SmtpAccount;
use App\Modules\Reporting\Services\ReportingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * docs/25-reporting.md — Reporting module. No dedicated model/policy exists
 * here (reporting reads across several modules' tables rather than owning
 * one), so authorization is done directly via `Gate::authorize(PermissionName)`
 * — the same plain-Gate style already used by
 * App\Modules\Settings\Http\Controllers\SettingsController and
 * App\Modules\Queues\Http\Controllers\QueueStatsController for the same
 * "no natural Eloquent model to hang a Policy off of" reason.
 *
 * docs/25-reporting.md §25.6 — read access (`reporting.view`) is granted to
 * every role including Viewer; export (`reporting.export`) requires the
 * separate, heavier permission (see database/seeders/RolePermissionSeeder.php).
 */
class ReportingController extends Controller
{
    public function __construct(private readonly ReportingService $reporting)
    {
    }

    /**
     * docs/25-reporting.md §25.2 — Delivery/Open/Click/Bounce Rate Reports'
     * shared summary shape (send volume, delivery/open/click/bounce rate)
     * over the filtered date range.
     */
    public function summary(Request $request): JsonResponse
    {
        Gate::authorize(PermissionName::ReportingView->value);

        return response()->json($this->reporting->summary($this->filters($request)));
    }

    /**
     * docs/25-reporting.md §25.2 — Campaign Comparison Report.
     */
    public function campaigns(Request $request): JsonResponse
    {
        Gate::authorize(PermissionName::ReportingView->value);

        return response()->json(['data' => $this->reporting->byCampaign($this->filters($request))]);
    }

    /**
     * docs/25-reporting.md §25.2 — SMTP Performance Report.
     */
    public function smtpAccounts(Request $request): JsonResponse
    {
        Gate::authorize(PermissionName::ReportingView->value);

        return response()->json(['data' => $this->reporting->bySmtpAccount($this->filters($request))]);
    }

    /**
     * docs/25-reporting.md §25.4 — CSV export, gated by the separate
     * `reporting.export` permission. Streamed directly via `fputcsv` rather
     * than a queued job (§25.4 reserves the queued-job path for result sets
     * "beyond a small inline result set" — the per-campaign breakdown this
     * exports is capped at 50 rows, see ReportingService::byCampaign()).
     */
    public function export(Request $request): StreamedResponse
    {
        Gate::authorize(PermissionName::ReportingExport->value);

        $rows = $this->reporting->byCampaign($this->filters($request));
        $filename = 'reporting-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'campaign_id', 'campaign_name', 'sent', 'delivered',
                'unique_opens', 'unique_clicks', 'bounced',
                'delivery_rate', 'open_rate', 'click_rate', 'bounce_rate',
            ]);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['campaign_id'],
                    $row['campaign_name'],
                    $row['sent'],
                    $row['delivered'],
                    $row['unique_opens'],
                    $row['unique_clicks'],
                    $row['bounced'],
                    $row['delivery_rate'],
                    $row['open_rate'],
                    $row['click_rate'],
                    $row['bounce_rate'],
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * @return array{date_from: string, date_to: string, campaign_id: int|null, smtp_account_id: int|null}
     */
    private function filters(Request $request): array
    {
        // No date range supplied defaults to a trailing 30-day window
        // (docs/25-reporting.md §25.1 "time window" — the doc does not
        // pin an exact default, so a rolling 30 days was chosen to keep
        // the live aggregate query bounded).
        $dateFrom = $request->query('date_from') ?: Carbon::now()->subDays(30)->toDateString();
        $dateTo = $request->query('date_to') ?: Carbon::now()->toDateString();

        return [
            'date_from' => (string) $dateFrom,
            'date_to' => (string) $dateTo,
            'campaign_id' => $this->resolveUuid(Campaign::class, $request->query('campaign_id')),
            'smtp_account_id' => $this->resolveUuid(SmtpAccount::class, $request->query('smtp_account_id')),
        ];
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     */
    private function resolveUuid(string $model, mixed $uuid): ?int
    {
        if (! is_string($uuid) || $uuid === '') {
            return null;
        }

        return $model::query()->where('uuid', $uuid)->value('id');
    }
}
