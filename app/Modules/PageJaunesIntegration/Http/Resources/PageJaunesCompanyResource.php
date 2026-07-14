<?php

namespace App\Modules\PageJaunesIntegration\Http\Resources;

use App\Modules\PageJaunesIntegration\DataTransferObjects\PageJaunesCompanyDto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * docs/29-api-specification.md §29.4 — GET /api/v1/pagejaunes/search.
 *
 * @property PageJaunesCompanyDto $resource
 */
class PageJaunesCompanyResource extends JsonResource
{
    public function __construct(PageJaunesCompanyDto $dto)
    {
        parent::__construct($dto);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PageJaunesCompanyDto $dto */
        $dto = $this->resource;

        return [
            'external_id' => $dto->externalId,
            'code' => $dto->code,
            'company_name' => $dto->companyName,
            'abv_company_name' => $dto->abvCompanyName,
            'legal_form_label' => $dto->legalFormLabel,
            'wilaya_label' => $dto->wilayaLabel,
            'locality_label' => $dto->localityLabel,
            'address' => $dto->address,
            'is_distributor' => $dto->isDistributor,
            'is_exporter' => $dto->isExporter,
            'is_importer' => $dto->isImporter,
            'is_producer' => $dto->isProducer,
            'emails' => array_map(
                fn ($e) => ['external_id' => $e->externalId, 'email' => $e->email],
                $dto->emails,
            ),
        ];
    }
}
