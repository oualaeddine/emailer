<?php

namespace App\Modules\PageJaunesIntegration\Services;

use App\Modules\PageJaunesIntegration\DataTransferObjects\PageJaunesCompanyDto;
use App\Modules\PageJaunesIntegration\Models\PageJaunesCompanyCache;
use App\Modules\PageJaunesIntegration\Repositories\PageJaunesCompanyRepository;
use Illuminate\Support\Facades\DB;

/**
 * docs/06-pagejaunes-integration.md §6.5 — Synchronization Strategy.
 * Upserts a {@see PageJaunesCompanyDto} (and its emails) into our own
 * cache tables — used both by the on-demand "search-time caching" path
 * (Recipients import UI) and the scheduled bulk sync job.
 */
class PageJaunesSyncService
{
    public function __construct(private readonly PageJaunesCompanyRepository $repository)
    {
    }

    public function upsertCompany(PageJaunesCompanyDto $dto): PageJaunesCompanyCache
    {
        return DB::transaction(function () use ($dto): PageJaunesCompanyCache {
            $cache = PageJaunesCompanyCache::query()->updateOrCreate(
                ['pagejaunes_company_id' => $dto->externalId],
                [
                    'pagejaunes_code' => $dto->code,
                    'company_name' => $dto->companyName,
                    'abv_company_name' => $dto->abvCompanyName,
                    'slug' => $dto->slug,
                    'legal_form_label' => $dto->legalFormLabel,
                    'country_label' => $dto->countryLabel,
                    'wilaya_label' => $dto->wilayaLabel,
                    'locality_label' => $dto->localityLabel,
                    'address' => $dto->address,
                    'about' => $dto->about,
                    'is_distributor' => $dto->isDistributor,
                    'is_exporter' => $dto->isExporter,
                    'is_importer' => $dto->isImporter,
                    'is_producer' => $dto->isProducer,
                    'is_customer' => $dto->isCustomer,
                    'nb_employee' => $dto->nbEmployee,
                    'main_picture_url' => $dto->mainPictureUrl,
                    'source_created_at' => $dto->sourceCreatedAt,
                    'source_deleted_at' => $dto->sourceDeletedAt,
                    'raw_source_data' => $dto->rawSourceData,
                    'synced_at' => now(),
                ],
            );

            foreach ($dto->emails as $emailDto) {
                $cache->emails()->updateOrCreate(
                    ['pagejaunes_email_id' => $emailDto->externalId],
                    ['email' => $emailDto->email, 'synced_at' => now()],
                );
            }

            return $cache;
        });
    }

    /**
     * @return array{synced: int, last_id: int|null}
     */
    public function syncBatch(int $sinceId, int $limit = 500): array
    {
        $companies = $this->repository->fetchBatch($sinceId, $limit);

        $lastId = $sinceId;
        foreach ($companies as $dto) {
            $this->upsertCompany($dto);
            $lastId = max($lastId, $dto->externalId);
        }

        return ['synced' => $companies->count(), 'last_id' => $companies->isEmpty() ? null : $lastId];
    }
}
