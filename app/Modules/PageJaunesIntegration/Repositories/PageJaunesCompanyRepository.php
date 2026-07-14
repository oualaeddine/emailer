<?php

namespace App\Modules\PageJaunesIntegration\Repositories;

use App\Modules\PageJaunesIntegration\DataTransferObjects\PageJaunesCompanyDto;
use App\Modules\PageJaunesIntegration\DataTransferObjects\PageJaunesEmailDto;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * docs/06-pagejaunes-integration.md §6.4 — Repository Layer.
 *
 * The ONLY place in the application allowed to query the `pagejaunes`
 * connection. Every method here is read-only (`SELECT` only, enforced by
 * the DB grant in staging/production per docs/28-security.md, and by
 * convention here — no query builder call in this class may be anything
 * other than a select). Always excludes soft-deleted source rows
 * (`deleted_at IS NULL`) per docs/06-pagejaunes-integration.md §6.2.
 */
class PageJaunesCompanyRepository
{
    private const CONNECTION = 'pagejaunes';

    /**
     * @return Collection<int, PageJaunesCompanyDto>
     */
    public function search(string $query, int $limit = 25, int $offset = 0): Collection
    {
        $rows = $this->baseQuery()
            ->where(function ($q) use ($query): void {
                $q->where('companies.company_name', 'like', "%{$query}%")
                    ->orWhere('companies.abv_company_name', 'like', "%{$query}%")
                    ->orWhere('companies.code', $query);
            })
            ->orderBy('companies.company_name')
            ->offset($offset)
            ->limit($limit)
            ->get();

        return $this->hydrateMany($rows);
    }

    public function findByExternalId(int $companyId): ?PageJaunesCompanyDto
    {
        $row = $this->baseQuery()->where('companies.id', $companyId)->first();

        return $row ? $this->hydrateMany(collect([$row]))->first() : null;
    }

    /**
     * Cursor-paginated sweep for the scheduled bulk sync job
     * (docs/06-pagejaunes-integration.md §6.5).
     *
     * @return Collection<int, PageJaunesCompanyDto>
     */
    public function fetchBatch(int $sinceId, int $limit = 500): Collection
    {
        $rows = $this->baseQuery()
            ->where('companies.id', '>', $sinceId)
            ->orderBy('companies.id')
            ->limit($limit)
            ->get();

        return $this->hydrateMany($rows);
    }

    private function baseQuery()
    {
        return DB::connection(self::CONNECTION)
            ->table('companies')
            ->leftJoin('companies_details', 'companies_details.company_id', '=', 'companies.id')
            ->leftJoin('legal_forms', 'legal_forms.id', '=', 'companies.legal_form_id')
            ->leftJoin('countries', 'countries.id', '=', 'companies.countrie_id')
            ->leftJoin('wilayas', 'wilayas.id', '=', 'companies.wilaya_id')
            ->leftJoin('localities', 'localities.id', '=', 'companies.localitie_id')
            ->whereNull('companies.deleted_at')
            ->select([
                'companies.id',
                'companies.code',
                'companies.company_name',
                'companies.abv_company_name',
                'companies.slug',
                'companies.Address as address',
                'companies.about',
                'companies.distributor',
                'companies.exportation',
                'companies.importation',
                'companies.producer',
                'companies.customer',
                'companies.main_picture',
                'companies.created_at',
                'companies.deleted_at',
                'legal_forms.label as legal_form_label',
                'countries.country as country_label',
                'wilayas.description_fr as wilaya_label',
                'localities.description_fr as locality_label',
                'companies_details.nb_employee',
                'companies_details.facebook',
                'companies_details.facebook_name',
                'companies_details.linkedin',
                'companies_details.linkedin_name',
                'companies_details.instagram',
                'companies_details.instagram_name',
                'companies_details.youtube',
                'companies_details.youtube_name',
                'companies_details.tiktok',
                'companies_details.tiktok_name',
                'companies_details.twitter',
                'companies_details.twitter_name',
                'companies_details.popup_image',
                'companies_details.ad_banner',
                'companies_details.geo_x',
                'companies_details.geo_y',
            ]);
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return Collection<int, PageJaunesCompanyDto>
     */
    private function hydrateMany(Collection $rows): Collection
    {
        if ($rows->isEmpty()) {
            return collect();
        }

        $companyIds = $rows->pluck('id')->all();

        $emailsByCompany = DB::connection(self::CONNECTION)
            ->table('emails')
            ->whereIn('company_id', $companyIds)
            ->whereNull('deleted_at')
            ->get()
            ->groupBy('company_id');

        return $rows->map(function (object $row) use ($emailsByCompany): PageJaunesCompanyDto {
            $emails = ($emailsByCompany->get($row->id) ?? collect())
                ->map(fn (object $e) => new PageJaunesEmailDto(externalId: (int) $e->id, email: $e->mail))
                ->values()
                ->all();

            return new PageJaunesCompanyDto(
                externalId: (int) $row->id,
                code: $row->code,
                companyName: $row->company_name,
                abvCompanyName: $row->abv_company_name,
                slug: $row->slug,
                legalFormLabel: $row->legal_form_label,
                countryLabel: $row->country_label,
                wilayaLabel: $row->wilaya_label,
                localityLabel: $row->locality_label,
                address: $row->address,
                about: $row->about,
                isDistributor: (bool) $row->distributor,
                isExporter: (bool) $row->exportation,
                isImporter: (bool) $row->importation,
                isProducer: (bool) $row->producer,
                isCustomer: (bool) $row->customer,
                nbEmployee: $row->nb_employee,
                mainPictureUrl: $row->main_picture,
                sourceCreatedAt: $row->created_at ? new \DateTimeImmutable($row->created_at) : null,
                sourceDeletedAt: $row->deleted_at ? new \DateTimeImmutable($row->deleted_at) : null,
                emails: $emails,
                rawSourceData: (array) $row,
            );
        });
    }
}
