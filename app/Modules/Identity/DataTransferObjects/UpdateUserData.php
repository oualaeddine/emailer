<?php

namespace App\Modules\Identity\DataTransferObjects;

/**
 * docs/29-api-specification.md §29.2 — PATCH /api/v1/users/{uuid}.
 * All fields optional — only present keys are applied (partial update).
 */
final readonly class UpdateUserData
{
    public function __construct(
        public ?string $name = null,
        public ?int $roleId = null,
        public ?bool $isActive = null,
    ) {
    }

    /**
     * @param  array{name?: string, role_id?: int, is_active?: bool}  $validated
     */
    public static function fromArray(array $validated): self
    {
        return new self(
            name: $validated['name'] ?? null,
            roleId: $validated['role_id'] ?? null,
            isActive: $validated['is_active'] ?? null,
        );
    }
}
