<?php

namespace App\Modules\Campaigns\Services\Checklist;

/**
 * docs/45-brevo-api-contracts.md §45.4.5 — one row of the pre-send checklist.
 */
final class ChecklistItem
{
    public const OK = 'ok';
    public const WARNING = 'warning';
    public const ERROR = 'error';
    public const PENDING = 'pending';
    public const SKIPPED = 'skipped';

    /**
     * @param  array<string, mixed>|null  $details
     */
    public function __construct(
        public readonly string $key,
        public readonly string $status,
        public readonly bool $blocking,
        public readonly string $message,
        public readonly ?array $details = null,
    ) {
    }

    /**
     * @return array{key: string, status: string, blocking: bool, message: string, details: array<string, mixed>|null}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'status' => $this->status,
            'blocking' => $this->blocking,
            'message' => $this->message,
            'details' => $this->details,
        ];
    }
}
