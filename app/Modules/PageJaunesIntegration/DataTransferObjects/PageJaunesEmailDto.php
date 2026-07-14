<?php

namespace App\Modules\PageJaunesIntegration\DataTransferObjects;

final readonly class PageJaunesEmailDto
{
    public function __construct(
        public int $externalId,
        public string $email,
    ) {
    }
}
