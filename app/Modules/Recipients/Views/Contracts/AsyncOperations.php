<?php

namespace App\Modules\Recipients\Views\Contracts;

use App\Modules\Identity\Models\User;
use App\Modules\Recipients\Views\Models\AsyncOperation;

/**
 * docs/45 §45.9 — shared async-operation tracker (WP-57 owns; WP-53/54/62/72 consume).
 */
interface AsyncOperations
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function start(string $type, ?User $user, array $payload, int $total = 0): AsyncOperation;

    public function progress(AsyncOperation $operation, int $processed, int $failed = 0, ?int $total = null): void;

    /**
     * @param  array<string, mixed>  $result
     */
    public function complete(AsyncOperation $operation, array $result = []): void;

    public function fail(AsyncOperation $operation, string $error): void;
}
