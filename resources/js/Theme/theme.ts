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
};

/**
 * Dark theme for Pages Jaunes DZ:
 * - Yellow accents (#FFD400) against dark background
 * - Black text (#111111) on primary yellow elements
 */
export const pageJaunesDarkTheme: Theme = {
    ...baseDark,
    colorBrandBackground: pjYellow,
    colorBrandBackgroundHover: '#FFE033',
    colorBrandBackgroundPressed: '#E6BF00',
    colorNeutralForegroundOnBrand: pjDark,
    colorNeutralStrokeOnBrand: pjDark,
    colorBrandForeground1: pjYellow,
    colorBrandForeground2: '#FFE233',
    colorBrandForegroundLink: pjYellow,
    colorBrandForegroundLinkHover: '#FFE033',
    colorBrandForegroundLinkPressed: '#E6BF00',
    colorBrandBackground2: '#2B2200',
    colorBrandStroke1: pjYellow,
};

