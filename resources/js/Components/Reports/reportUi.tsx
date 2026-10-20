import { useEffect, useState, type RefObject } from 'react';
import { fr } from '@/Lib/i18n/fr';
import type { DeviceType, RateSet, ReportCampaignHeader } from '@/Lib/types/reports';

/**
 * Shared helpers for Campaigns/Report (docs/43 §43.4.15, docs/45 §45.6.5).
 *
 * `reportMissingText` holds the interface strings the frozen fr.ts
 * `campaignReport` namespace does not have yet; they are reported in WP-71's
 * return so the orchestrator can move them to fr.ts between waves.
 */
export const reportMissingText = {
    showTable: 'Afficher le tableau',
    showChart: 'Afficher le graphique',
    noData: 'Aucune donnée pour le moment.',
    domain: 'Domaine',
    country: 'Pays',
    client: 'Client de messagerie',
    device: 'Appareil',
    hour: 'Heure',
    shareOfOpens: 'Part des ouvertures',
    email: 'E-mail',
    metric: 'Indicateur',
    clickMapFallback: 'L’aperçu de l’e-mail ne peut pas être annoté : liens classés par part des clics.',
    emailUnavailable: 'Le contenu de l’e-mail n’est pas disponible.',
    linkClicks: '%s clics uniques · %s clics totaux',
    compareMax: 'Vous pouvez comparer au maximum 4 autres campagnes.',
    compareRun: 'Lancer la comparaison',
    compareCurrent: 'Campagne actuelle',
} as const;

export const DEVICE_ORDER: DeviceType[] = ['desktop', 'mobile', 'tablet', 'unknown'];

/** Rates where "higher than the account average" is bad news. */
export const LOWER_IS_BETTER: ReadonlySet<keyof RateSet> = new Set(['unsubscribe_rate', 'bounce_rate', 'complaint_rate']);

/** Compact axis ticks: 0 / 950 / 1,2 k / 12 k / 1,3 M. */
export function formatCompact(value: number): string {
    return new Intl.NumberFormat('fr-FR', { notation: 'compact', maximumFractionDigits: 1 }).format(value);
}

/** Brevo `opensRate` is already a percentage 0–100. */
export function formatPercentValue(percent: number | null | undefined): string {
    if (percent === null || percent === undefined || Number.isNaN(percent)) {
        return fr.common.emptyValue;
    }

    return fr.common.percent.replace('%s', percent.toLocaleString('fr-FR', { minimumFractionDigits: 1, maximumFractionDigits: 1 }));
}

/** Safe ratio (0 when the denominator is 0). */
export function ratio(part: number, whole: number): number {
    return whole > 0 ? part / whole : 0;
}

let regionNames: Intl.DisplayNames | null | undefined;

/** ISO-3166 alpha-2 → French country name ("unknown" → Inconnu). */
export function countryName(code: string): string {
    if (!code || code.toLowerCase() === 'unknown') {
        return fr.common.unknown;
    }
    if (regionNames === undefined) {
        try {
            regionNames = new Intl.DisplayNames(['fr'], { type: 'region' });
        } catch {
            regionNames = null;
        }
    }
    try {
        return regionNames?.of(code.toUpperCase()) ?? code;
    } catch {
        return code;
    }
}

export function headerSentAt(campaign: ReportCampaignHeader): string | null {
    return campaign.sent_at ?? campaign.completed_at;
}

/**
 * Chart palette (dataviz reference instance, validated with
 * scripts/validate_palette.js: slots 1–3 pass light and dark; light slot 3 is
 * below 3:1 so every chart ships direct labels and a table view).
 */
export interface ChartPalette {
    series: [string, string, string];
    dark: boolean;
}

const LIGHT_SERIES: ChartPalette['series'] = ['#2a78d6', '#eb6834', '#1baf7a'];
const DARK_SERIES: ChartPalette['series'] = ['#3987e5', '#d95926', '#199e70'];

function luminanceOf(color: string): number | null {
    const value = color.trim();
    let r: number;
    let g: number;
    let b: number;
    const hex = /^#([0-9a-f]{6})$/i.exec(value);
    const rgb = /^rgba?\(\s*(\d+)[,\s]+(\d+)[,\s]+(\d+)/i.exec(value);
    if (hex) {
        const n = parseInt(hex[1], 16);
        r = (n >> 16) & 255;
        g = (n >> 8) & 255;
        b = n & 255;
    } else if (rgb) {
        r = Number(rgb[1]);
        g = Number(rgb[2]);
        b = Number(rgb[3]);
    } else {
        return null;
    }

    return (0.2126 * r + 0.7152 * g + 0.0722 * b) / 255;
}

/** Picks the light or dark steps from the Fluent surface the chart renders on. */
export function useChartPalette(ref: RefObject<HTMLElement | null>): ChartPalette {
    const [dark, setDark] = useState(false);

    useEffect(() => {
        const element = ref.current;
        if (!element) {
            return;
        }
        const surface = getComputedStyle(element).getPropertyValue('--colorNeutralBackground1');
        const luminance = luminanceOf(surface);
        setDark(luminance !== null && luminance < 0.5);
    }, [ref]);

    return { series: dark ? DARK_SERIES : LIGHT_SERIES, dark };
}

/** "Nice" axis ticks from 0 to ≥ max. */
export function niceTicks(max: number, count = 4): number[] {
    if (max <= 0) {
        return [0, 1];
    }
    const rough = max / count;
    const magnitude = 10 ** Math.floor(Math.log10(rough));
    const residual = rough / magnitude;
    const step = (residual > 5 ? 10 : residual > 2 ? 5 : residual > 1 ? 2 : 1) * magnitude;
    const top = Math.ceil(max / step) * step;
    const ticks: number[] = [];
    for (let v = 0; v <= top + step / 2; v += step) {
        ticks.push(Math.round(v * 1e6) / 1e6);
    }

    return ticks;
}
