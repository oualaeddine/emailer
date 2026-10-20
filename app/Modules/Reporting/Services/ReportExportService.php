<?php

namespace App\Modules\Reporting\Services;

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Recipients\Views\Services\ContactExportService;
use Illuminate\Support\Str;

/**
 * docs/45-brevo-api-contracts.md §45.6.5 — CSV export of the aggregates of one report tab / statistics
 * table: `;`-separated, UTF-8 BOM, formula-neutralised cells (same rule as the contacts export).
 */
class ReportExportService
{
    public function __construct(
        private readonly ReportQueryService $reports,
        private readonly StatisticsService $statistics,
    ) {}

    /**
     * @return list<array{title: string, headers: list<string>, rows: list<list<mixed>>}>
     */
    public function campaignSections(Campaign $campaign, string $tab): array
    {
        $data = match ($tab) {
            'overview' => $this->reports->overview($campaign),
            'deliverability' => $this->reports->deliverability($campaign),
            'opens' => $this->reports->opens($campaign),
            'clicks' => $this->reports->clicks($campaign),
            'unsubscriptions' => $this->reports->unsubscriptions($campaign),
        };

        $sections = [];
        $kpis = match ($tab) {
            'overview', 'deliverability' => $this->kv($data['globalStats'] ?? []) + [],
            'opens' => $this->kv(array_intersect_key($data, array_flip(['viewed', 'uniqueViews', 'trackableViews', 'estimatedViews', 'appleMppOpens', 'opensRate']))),
            'clicks' => $this->kv(array_intersect_key($data, array_flip(['uniqueClicks', 'clickers', 'total_clicks', 'click_rate', 'click_to_open_rate']))),
            'unsubscriptions' => $this->kv(array_intersect_key($data, array_flip(['unsubscriptions', 'complaints', 'unsubscribe_rate', 'complaint_rate']))),
        };
        $sections[] = ['title' => 'Indicateurs', 'headers' => ['Indicateur', 'Valeur'], 'rows' => $kpis];

        if (isset($data['rates'])) {
            $sections[] = ['title' => 'Taux', 'headers' => ['Taux', 'Valeur'], 'rows' => $this->kv($data['rates'])];
        }

        match ($tab) {
            'overview' => $this->overviewSections($data, $sections),
            'deliverability' => $this->deliverabilitySections($data, $sections),
            'opens' => $this->opensSections($data, $sections),
            'clicks' => $this->clicksSections($data, $sections),
            'unsubscriptions' => $this->unsubSections($data, $sections),
        };

        return $sections;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $sections
     */
    private function overviewSections(array $data, array &$sections): void
    {
        $sections[] = ['title' => 'Liens (top 10)', 'headers' => ['URL', 'Clics uniques', 'Clics totaux', 'Part'],
            'rows' => array_map(fn (array $l): array => [$l['url'], $l['unique_clicks'], $l['total_clicks'], $l['share']], $data['links'])];
        $sections[] = ['title' => 'Audience', 'headers' => ['Cible', 'Nom', 'Envoyés', 'Délivrés', 'Ouvreurs uniques', 'Cliqueurs'],
            'rows' => array_map(fn (array $e): array => [$e['segmentId'] !== null ? 'segment' : 'liste', $e['name'], $e['sent'], $e['delivered'], $e['uniqueViews'], $e['uniqueClicks']], $data['campaignStats'])];
        $sections[] = $this->timeseriesSection($data['timeseries']);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $sections
     */
    private function deliverabilitySections(array $data, array &$sections): void
    {
        $sections[] = ['title' => 'Rebonds', 'headers' => ['Type', 'Raison', 'Nombre'],
            'rows' => array_map(fn (array $r): array => [$r['type'], $r['reason'], $r['count']], $data['bounce_reasons'])];
        $sections[] = ['title' => 'Comptes SMTP', 'headers' => ['Compte', 'Envoyés', 'Délivrés', 'Hard bounces', 'Soft bounces'],
            'rows' => array_map(fn (array $r): array => [$r['name'], $r['sent'], $r['delivered'], $r['hardBounces'], $r['softBounces']], $data['by_smtp_account'])];
        $sections[] = $this->domainSection((array) $data['statsByDomain']);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $sections
     */
    private function opensSections(array $data, array &$sections): void
    {
        $sections[] = ['title' => 'Par heure du jour', 'headers' => ['Heure', 'Ouvreurs uniques'],
            'rows' => array_map(fn (array $r): array => [$r['hour'], $r['uniqueViews']], $data['by_hour_of_day'])];
        $sections[] = $this->domainSection((array) $data['statsByDomain']);
        $sections[] = $this->breakdownSection('Navigateurs / clients', (array) $data['statsByBrowser']);
        $sections[] = $this->breakdownSection('Pays', (array) $data['statsByCountry']);
        $sections[] = $this->timeseriesSection($data['timeseries']);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $sections
     */
    private function clicksSections(array $data, array &$sections): void
    {
        $sections[] = ['title' => 'Liens', 'headers' => ['Identifiant', 'URL', 'Libellé', 'Clics uniques', 'Clics totaux', 'Part'],
            'rows' => array_map(fn (array $l): array => [$l['link_id'], $l['url'], $l['label'], $l['unique_clicks'], $l['total_clicks'], $l['share']], $data['links'])];
        $sections[] = $this->timeseriesSection($data['timeseries']);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $sections
     */
    private function unsubSections(array $data, array &$sections): void
    {
        $sections[] = ['title' => 'Par liste', 'headers' => ['Liste', 'Désinscriptions', 'Plaintes'],
            'rows' => array_map(fn (array $r): array => [$r['name'], $r['unsubscriptions'], $r['complaints']], $data['by_list'])];
        $sections[] = ['title' => 'Motifs', 'headers' => ['Motif', 'Nombre'],
            'rows' => array_map(fn (array $r): array => [$r['reason'], $r['count']], $data['reasons'])];
    }

    /**
     * @param  array<string, mixed>  $values
     * @return list<list<mixed>>
     */
    private function kv(array $values): array
    {
        $rows = [];
        foreach ($values as $k => $v) {
            if (is_scalar($v) || $v === null) {
                $rows[] = [$k, $v];
            }
        }

        return $rows;
    }

    /**
     * @param  array{points: list<array<string, mixed>>}  $series
     * @return array{title: string, headers: list<string>, rows: list<list<mixed>>}
     */
    private function timeseriesSection(array $series): array
    {
        return ['title' => 'Évolution', 'headers' => ['Date', 'Envoyés', 'Délivrés', 'Ouvertures', 'Ouvreurs uniques', 'Cliqueurs uniques', 'Désinscriptions'],
            'rows' => array_map(fn (array $p): array => [$p['t'], $p['sent'], $p['delivered'], $p['viewed'], $p['uniqueViews'], $p['uniqueClicks'], $p['unsubscriptions']], $series['points'])];
    }

    /**
     * @param  array<string, array<string, int>>  $domains
     * @return array{title: string, headers: list<string>, rows: list<list<mixed>>}
     */
    private function domainSection(array $domains): array
    {
        $rows = [];
        foreach ($domains as $domain => $d) {
            $rows[] = [$domain, $d['sent'], $d['delivered'], $d['hardBounces'], $d['softBounces'], $d['uniqueViews'], $d['uniqueClicks']];
        }

        return ['title' => 'Domaines', 'headers' => ['Domaine', 'Envoyés', 'Délivrés', 'Hard bounces', 'Soft bounces', 'Ouvreurs uniques', 'Cliqueurs uniques'], 'rows' => $rows];
    }

    /**
     * @param  array<string, array<string, int>>  $items
     * @return array{title: string, headers: list<string>, rows: list<list<mixed>>}
     */
    private function breakdownSection(string $title, array $items): array
    {
        $rows = [];
        foreach ($items as $name => $b) {
            $rows[] = [$name, $b['viewed'], $b['uniqueViews'], $b['uniqueClicks']];
        }

        return ['title' => $title, 'headers' => ['Nom', 'Ouvertures', 'Ouvreurs uniques', 'Cliqueurs uniques'], 'rows' => $rows];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return list<array{title: string, headers: list<string>, rows: list<list<mixed>>}>
     */
    public function statisticsSections(array $input, string $table): array
    {
        if ($table === 'lists') {
            $rows = array_map(fn (array $r): array => [$r['name'], $r['contact_count'], ...$this->statCells($r)], $this->statistics->lists($input));

            return [['title' => 'Listes', 'headers' => ['Liste', 'Contacts', ...$this->statHeaders()], 'rows' => $rows]];
        }

        $rows = array_map(fn (array $r): array => [$r['name'], $r['sent_at'], implode(', ', $r['tags']), ...$this->statCells($r)], $this->statistics->allCampaignRows($input));

        return [['title' => 'Campagnes', 'headers' => ['Campagne', 'Envoyée le', 'Tags', ...$this->statHeaders()], 'rows' => $rows]];
    }

    /**
     * @return list<string>
     */
    private function statHeaders(): array
    {
        return ['Envoyés', 'Délivrés', 'Ouvreurs uniques', 'Cliqueurs uniques', 'Désinscriptions', 'Hard bounces', 'Soft bounces', 'Taux d’ouverture', 'Taux de clic'];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<mixed>
     */
    private function statCells(array $row): array
    {
        $g = $row['globalStats'];

        return [$g['sent'], $g['delivered'], $g['uniqueViews'], $g['uniqueClicks'], $g['unsubscriptions'], $g['hardBounces'], $g['softBounces'],
            $row['rates']['open_rate'], $row['rates']['click_rate']];
    }

    /**
     * Writes the sections to `$handle` as `;` CSV (UTF-8 BOM first); returns the number of data rows.
     *
     * @param  resource  $handle
     * @param  list<array{title: string, headers: list<string>, rows: list<list<mixed>>}>  $sections
     */
    public function write($handle, array $sections): int
    {
        fwrite($handle, "\xEF\xBB\xBF");
        $count = 0;
        $clean = fn (array $row): array => array_map(fn ($v) => ContactExportService::neutralizeFormula($v), $row);

        foreach ($sections as $i => $section) {
            if ($i > 0) {
                fputcsv($handle, [], ';');
            }
            fputcsv($handle, $clean([$section['title']]), ';');
            fputcsv($handle, $clean($section['headers']), ';');
            foreach ($section['rows'] as $row) {
                fputcsv($handle, $clean($row), ';');
                $count++;
            }
        }

        return $count;
    }

    public function campaignFilename(Campaign $campaign, string $tab): string
    {
        return 'rapport-'.Str::slug($campaign->name ?: 'campagne').'-'.$tab.'-'.now()->format('Ymd-His').'.csv';
    }
}
