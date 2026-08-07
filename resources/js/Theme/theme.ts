import { createLightTheme, createDarkTheme, type BrandVariants, type Theme } from '@fluentui/react-components';

/**
 * docs/07-ui-design.md §7.2 — Design Tokens, §7.11 — Theming & Branding.
 * Single brand color generates the full light/dark ramp so both themes
 * stay in sync from one input. Matches Business Leads Algeria's brand red
 * (#DF0A0A, ramp step 90) until Settings → Branding lets the organization
 * configure its own.
 */
const brand: BrandVariants = {
    10: '#0e0202',
    20: '#210303',
    30: '#3e0404',
    40: '#5c0505',
    50: '#7a0606',
    60: '#970707',
    70: '#b40808',
    80: '#d10a0a',
    90: '#df0a0a',
    100: '#f01919',
    110: '#ef3939',
    120: '#ed5a5a',
    130: '#eb7a7a',
    140: '#ea9999',
    150: '#ebb7b7',
    160: '#eed3d3',
};

/** BLA secondary navy — used for accents outside the Fluent brand ramp (docs/07 §7.11). */
export const blaSecondary = '#111d5e';

export const pageJaunesLightTheme: Theme = createLightTheme(brand);
export const pageJaunesDarkTheme: Theme = createDarkTheme(brand);
