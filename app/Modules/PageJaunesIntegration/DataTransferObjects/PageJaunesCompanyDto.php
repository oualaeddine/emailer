<?php

namespace App\Modules\PageJaunesIntegration\DataTransferObjects;

/**
 * docs/06-pagejaunes-integration.md §6.4 — the stable shape
 * PageJaunesCompanyRepository maps every raw external row into. No other
 * code in the application ever sees the source's raw column names
 * (Anticorruption Layer, docs/35-domain-driven-design.md §35.4).
 */
final readonly class PageJaunesCompanyDto
{
    /**
     * @param  list<PageJaunesEmailDto>  $emails
     */
    public function __construct(
        public int $externalId,
        public string $code,
        public ?string $companyName,
        public ?string $abvCompanyName,
        public ?string $slug,
        public ?string $legalFormLabel,
        public ?string $countryLabel,
        public ?string $wilayaLabel,
        public ?string $localityLabel,
        public ?string $address,
        public ?string $about,
        public bool $isDistributor,
        public bool $isExporter,
        public bool $isImporter,
        public bool $isProducer,
        public bool $isCustomer,
        public ?string $nbEmployee,
        public ?string $mainPictureUrl,
        public ?\DateTimeImmutable $sourceCreatedAt,
        public ?\DateTimeImmutable $sourceDeletedAt,
        public array $emails,
        public array $rawSourceData,
    ) {
    }
}
