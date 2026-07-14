import { createLightTheme, createDarkTheme, type BrandVariants, type Theme } from '@fluentui/react-components';

/**
 * docs/07-ui-design.md §7.2 — Design Tokens, §7.11 — Theming & Branding.
 * Single brand color generates the full light/dark ramp so both themes
 * stay in sync from one input. The default below is a placeholder brand
 * color until Settings → Branding (a later module) allows the
 * organization to configure its own.
 */
const brand: BrandVariants = {
    10: '#040305',
    20: '#161122',
    30: '#24193d',
    40: '#301f54',
    50: '#3d266d',
    60: '#4b2c87',
    70: '#5932a2',
    80: '#6838bd',
    90: '#7740d6',
    100: '#8752dc',
    110: '#9765e0',
    120: '#a878e4',
    130: '#b98be9',
    140: '#c99eed',
    150: '#dab1f1',
    160: '#eac4f5',
};

export const pageJaunesLightTheme: Theme = createLightTheme(brand);
export const pageJaunesDarkTheme: Theme = createDarkTheme(brand);
