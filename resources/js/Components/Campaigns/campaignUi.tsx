import { Badge } from '@fluentui/react-components';
import { isAxiosError } from 'axios';
import { fr } from '@/Lib/i18n/fr';
import type { CampaignStatusV2, CampaignV2 } from '@/Lib/types/campaigns';

/**
 * Shared helpers for the Brevo-parity campaign pages (docs/43 §43.4.1–43.4.2,
 * docs/45 §45.4.2): status chip, French formatting, API error extraction.
 */
export type CampaignStatusChipKey = CampaignStatusV2 | 'archived';

type BadgeColor = 'subtle' | 'informative' | 'warning' | 'success' | 'danger' | 'important' | 'brand';

const STATUS_COLOR: Record<CampaignStatusChipKey, BadgeColor> = {
    draft: 'subtle',
    scheduled: 'informative',
    running: 'brand',
    paused: 'warning',
    suspended: 'warning',
    completed: 'success',
    cancelled: 'danger',
    archived: 'subtle',
};

export function campaignStatusKey(campaign: Pick<CampaignV2, 'status' | 'is_archived'>): CampaignStatusChipKey {
    return campaign.is_archived ? 'archived' : campaign.status;
}

export function CampaignStatusChip({ campaign }: { campaign: Pick<CampaignV2, 'status' | 'is_archived' | 'suspended_reason'> }) {
    const key = campaignStatusKey(campaign);
    const title =
        key === 'suspended' && campaign.suspended_reason ? fr.campaigns.suspendedReasons[campaign.suspended_reason] : undefined;

    return (
        <Badge appearance="tint" color={STATUS_COLOR[key]} title={title}>
            {fr.campaigns.statusLabels[key]}
        </Badge>
    );
}

/** Replaces each `%d` / `%s` placeholder of a fr.ts string, in order. */
export function formatText(template: string, ...args: Array<string | number>): string {
    let index = 0;

    return template.replace(/%[ds]/g, (match) => {
        if (index >= args.length) {
            return match;
        }
        const value = args[index++];

        return typeof value === 'number' ? value.toLocaleString('fr-FR') : value;
    });
}

export function formatDateTime(value: string | null | undefined): string {
    return value ? new Date(value).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' }) : fr.common.emptyValue;
}

/** Ratio 0–1 → "41,0 %". */
export function formatRate(ratio: number | null | undefined): string {
    if (ratio === null || ratio === undefined) {
        return fr.common.emptyValue;
    }

    return formatText(fr.common.percent, (ratio * 100).toLocaleString('fr-FR', { maximumFractionDigits: 1, minimumFractionDigits: 1 }));
}

export function formatCount(value: number | null | undefined): string {
    return value === null || value === undefined ? fr.common.emptyValue : value.toLocaleString('fr-FR');
}

/** French error message from the doc 29 §29.1 envelope, mapped by HTTP status otherwise. */
export function apiErrorMessage(error: unknown): string {
    if (isAxiosError(error)) {
        const data = error.response?.data as { message?: unknown } | undefined;
        if (data && typeof data.message === 'string' && data.message.length > 0) {
            return data.message;
        }
        switch (error.response?.status) {
            case 403:
                return fr.common.forbidden;
            case 404:
                return fr.common.notFound;
            case 409:
                return fr.common.conflict;
            default:
                break;
        }
    }

    return fr.common.genericError;
}

export function apiStatus(error: unknown): number | null {
    return isAxiosError(error) ? (error.response?.status ?? null) : null;
}

/** First validation message per field of a 422 response (`errors.field = [msg]`). */
export function apiValidationErrors(error: unknown): Record<string, string> {
    if (!isAxiosError(error) || error.response?.status !== 422) {
        return {};
    }
    const errors = (error.response.data as { errors?: Record<string, unknown> } | undefined)?.errors ?? {};
    const result: Record<string, string> = {};
    for (const [key, value] of Object.entries(errors)) {
        if (Array.isArray(value) && typeof value[0] === 'string') {
            result[key] = value[0];
        } else if (typeof value === 'string') {
            result[key] = value;
        }
    }

    return result;
}

/** A loose e-mail check for test-send inputs (the server validates for real). */
export function isEmailLike(value: string): boolean {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
}

export function campaignSetupUrl(id: string): string {
    return `/campaigns/${id}/setup`;
}

export function campaignDesignUrl(id: string): string {
    return `/campaigns/${id}/design`;
}
