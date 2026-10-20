import { createLightTheme, createDarkTheme, type BrandVariants, type Theme } from '@fluentui/react-components';

/**
 * docs/07-ui-design.md §7.2 — Design Tokens, §7.11 — Theming & Branding.
 * Official brand color palette for Pages Jaunes DZ (#FFD400 yellow, #111111 deep charcoal/black).
 * Single brand color generates the full light/dark ramp so both themes
 * stay in sync.
 */
const brand: BrandVariants = {
    10: '#120E00',
    20: '#241C00',
    30: '#382C00',
    40: '#4F3E00',
    50: '#695200',
    60: '#856800',
    70: '#A68200',
    80: '#FFD400',
    90: '#FFD91A',
    100: '#FFDE33',
    110: '#FFE34D',
    120: '#FFE966',
    130: '#FFEE80',
    140: '#FFF399',
    150: '#FFF8B3',
    160: '#FFFDE6',
};

/** Pages Jaunes DZ signature yellow */
export const pjYellow = '#FFD400';

/** Pages Jaunes DZ primary text and dark neutral */
export const pjDark = '#111111';

/** Pages Jaunes DZ secondary mint/teal accent (from pagesjaunes-dz.com) */
export const pjTeal = '#5ECFB1';

/** Pages Jaunes secondary deep navy accent */
export const pjNavy = '#1B2A4A';

/** Legacy alias for secondary navy */
export const blaSecondary = pjNavy;

const baseLight = createLightTheme(brand);
const baseDark = createDarkTheme(brand);

/**
 * Light theme for Pages Jaunes DZ:
 * - Yellow background (#FFD400) for primary buttons and brand marks
 * - Deep black (#111111) text on yellow for 13.2:1 contrast (WCAG AAA)
 * - Deep amber/gold (#784D00) for links and brand foreground on white backgrounds (7.3:1 contrast)
 * - Dark ticks/thumbs on yellow checkboxes and switches
 */
export const pageJaunesLightTheme: Theme = {
    ...baseLight,
    colorNeutralForegroundOnBrand: pjDark,
    colorNeutralStrokeOnBrand: pjDark,
    colorBrandForegroundLink: '#784D00',
    colorBrandForegroundLinkHover: '#5C3A00',
    colorBrandForegroundLinkPressed: '#3D2700',
    colorBrandForeground1: '#784D00',
    colorBrandForeground2: '#5C3A00',
    colorBrandStroke1: '#E6BF00',
    colorBrandBackgroundHover: '#F2C900',
    colorBrandBackgroundPressed: '#CCA900',
    // Checkbox tick, Switch thumb and multi-select ticks sit on the yellow
    // compound-brand background: white on #FFD400 is 1.4:1, #111111 is 13.2:1.
    // (No inverted Toast is used in the app, the other consumer of this token.)
    colorNeutralForegroundInverted: pjDark,
    colorNeutralForegroundInvertedHover: pjDark,
    colorNeutralForegroundInvertedPressed: pjDark,
    colorNeutralForegroundInvertedSelected: pjDark,
};

/**
 * Shifts a pure grey (#rrggbb with r = g = b) towards a warm, slightly
 * yellow-brown neutral so the dark surfaces read as « charbon chaud » next to
 * the brand yellow instead of a cold flat grey. Non-grey values (alpha
 * colours, brand ramps, palettes) are returned untouched.
 */
function warmGrey(value: string): string {
    const match = /^#([0-9a-f]{2})\1\1([0-9a-f]{2})?$/i.exec(value);
    if (!match) {
        return value;
    }
    const v = parseInt(match[1], 16);
    if (v === 0 || v === 255) {
        return value;
    }
    // Darker surfaces get a little more tint; light greys (text) barely any.
    const [dr, dg, db] = v < 160 ? [4, 2, -3] : [2, 0, -4];
    const clamp = (n: number) => Math.max(0, Math.min(255, n)).toString(16).padStart(2, '0');

    return `#${clamp(v + dr)}${clamp(v + dg)}${clamp(v + db)}${match[2] ?? ''}`;
}

const warmNeutralTokens = /^color(NeutralBackground|NeutralCardBackground|SubtleBackground|NeutralStroke(?!OnBrand)|NeutralStencil|NeutralForeground[2-5](?!Brand)|NeutralForegroundDisabled)/;

const warmDark = Object.fromEntries(
    Object.entries(baseDark).map(([key, value]) => [
        key,
        typeof value === 'string' && warmNeutralTokens.test(key) ? warmGrey(value) : value,
    ]),
) as unknown as Theme;

/**
 * Dark theme for Pages Jaunes DZ:
 * - warm charcoal neutrals (see warmGrey) rather than pure grey;
 * - the signature yellow (#FFD400) stays the accent: primary buttons, badges
 *   and selected states are yellow with #111111 text (13.2:1, WCAG AAA);
 * - brand foregrounds (links, selected tabs, icons) are yellow on charcoal
 *   (≈ 11:1 on colorNeutralBackground1);
 * - tokens meant for light surfaces (colorBrandForegroundOnLight / Inverted)
 *   keep the dark amber used by the light theme.
 */
export const pageJaunesDarkTheme: Theme = {
    ...warmDark,
    colorBrandBackground: pjYellow,
    colorBrandBackgroundHover: '#FFE033',
    colorBrandBackgroundPressed: '#E6BF00',
    colorBrandBackgroundSelected: '#F2C900',
    colorNeutralForegroundOnBrand: pjDark,
    colorNeutralStrokeOnBrand: pjDark,
    colorBrandForeground1: pjYellow,
    colorBrandForeground2: '#FFE233',
    colorBrandForegroundLink: pjYellow,
    colorBrandForegroundLinkHover: '#FFE033',
    colorBrandForegroundLinkPressed: '#E6BF00',
    colorBrandForegroundLinkSelected: pjYellow,
    colorCompoundBrandForeground1: pjYellow,
    colorCompoundBrandBackground: pjYellow,
    colorCompoundBrandStroke: pjYellow,
    colorNeutralForegroundInverted: pjDark,
    colorNeutralForegroundInvertedHover: pjDark,
    colorNeutralForegroundInvertedPressed: pjDark,
    colorNeutralForegroundInvertedSelected: pjDark,
    colorBrandForegroundOnLight: '#784D00',
    colorBrandForegroundOnLightHover: '#5C3A00',
    colorBrandForegroundOnLightPressed: '#3D2700',
    colorBrandForegroundOnLightSelected: '#5C3A00',
    colorBrandForegroundInverted: '#784D00',
    colorBrandForegroundInvertedHover: '#5C3A00',
    colorBrandForegroundInvertedPressed: '#3D2700',
    colorBrandBackground2: '#2E2500',
    colorBrandBackground2Hover: '#3D3100',
    colorBrandBackground2Pressed: '#241C00',
    colorBrandStroke1: pjYellow,
    colorBrandStroke2: '#6B5600',
};

export type ThemeMode = 'light' | 'dark';

export const themes: Record<ThemeMode, Theme> = {
    light: pageJaunesLightTheme,
    dark: pageJaunesDarkTheme,
};
