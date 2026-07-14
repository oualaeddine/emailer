<?php

namespace App\Modules\Identity\DataTransferObjects;

/**
 * docs/29-api-specification.md §29.2 — POST /api/v1/users.
 */
final readonly class CreateUserData
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
        public int $roleId,
    ) {
    }

    /**
     * @param  array{name: string, email: string, password: string, role_id: int}  $validated
     */
    public static function fromArray(array $validated): self
    {
        return new self(
            name: $validated['name'],
            email: $validated['email'],
            password: $validated['password'],
            roleId: $validated['role_id'],
        );
    }
}
