<?php

namespace App\Modules\Recipients\DataTransferObjects;

use App\Domain\Enums\RecipientSource;

/**
 * docs/13-recipient-management.md §13.1 — recipient creation, any source.
 */
final readonly class CreateRecipientData
{
    /**
     * @param  array<string, mixed>  $customFields
     */
    public function __construct(
        public string $email,
        public RecipientSource $source,
        public ?string $firstName = null,
        public ?string $lastName = null,
        public ?string $companyName = null,
        public ?int $pageJaunesCompanyCacheId = null,
        public array $customFields = [],
    ) {
    }
}
