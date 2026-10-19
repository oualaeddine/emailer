<?php

namespace App\Modules\Campaigns\Enums;

/**
 * docs/15-campaign-management.md §15.2 step 4 — "Sending Configuration":
 * `auto_rotate` (Rotation Engine picks the account per message, see
 * docs/17-delivery-engine.md/docs/18-smtp-rotation.md) vs. pinning the
 * whole campaign to a single `smtp_accounts` row.
 *
 * docs/45 §45.4.2 exposes these as `rotation` / `single`; both spellings
 * are accepted on input.
 */
enum CampaignSmtpStrategy: string
{
    case AutoRotate = 'auto_rotate';
    case Pinned = 'pinned';

    public function apiValue(): string
    {
        return $this === self::AutoRotate ? 'rotation' : 'single';
    }

    public static function fromApi(string $value): self
    {
        return match ($value) {
            'rotation', 'auto_rotate' => self::AutoRotate,
            'single', 'pinned' => self::Pinned,
        };
    }

    /**
     * @return list<string>
     */
    public static function acceptedInputValues(): array
    {
        return ['rotation', 'single', 'auto_rotate', 'pinned'];
    }
}
