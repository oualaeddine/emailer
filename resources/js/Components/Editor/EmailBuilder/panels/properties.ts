/**
 * WP-60 — Fluent "Propriétés" panel definitions (docs/46 §5, §7): per
 * grapesjs-mjml component type, the MJML attributes edited with Fluent
 * fields. Composed blocks (`title`, `video`, `logo`) get their own list via
 * the `pjBlock` component prop.
 */
import type { EmailBlockType } from '@/Lib/types/editor';
import { text } from '../strings';

export type PropertyFieldKind = 'text' | 'color' | 'select' | 'url';

export interface PropertyOption {
    value: string;
    label: string;
}

export interface PropertyFieldDef {
    /** MJML attribute name. */
    name: string;
    label: string;
    kind: PropertyFieldKind;
    placeholder?: string;
    options?: PropertyOption[];
}

/** Preset that writes several attributes at once (one undo step). */
export interface PropertyPreset {
    label: string;
    options: { value: string; label: string; attributes: Record<string, string> }[];
    /** Finds the current option from the component attributes. */
    current: (attributes: Record<string, string>) => string;
}

const f = text.fields;

const align: PropertyFieldDef = {
    name: 'align',
    label: f.align,
    kind: 'select',
    options: [
        { value: 'left', label: f.alignLeft },
        { value: 'center', label: f.alignCenter },
        { value: 'right', label: f.alignRight },
    ],
};
const padding: PropertyFieldDef = { name: 'padding', label: f.padding, kind: 'text', placeholder: '10px 25px' };
const color = (name: string, label: string): PropertyFieldDef => ({ name, label, kind: 'color', placeholder: '#242424' });
const field = (name: string, label: string, placeholder?: string): PropertyFieldDef => ({ name, label, kind: 'text', placeholder });
const url = (name: string, label: string): PropertyFieldDef => ({ name, label, kind: 'url', placeholder: 'https://' });

const fontWeight: PropertyFieldDef = {
    name: 'font-weight',
    label: f.fontWeight,
    kind: 'select',
    options: [
        { value: '400', label: f.weightNormal },
        { value: '600', label: f.weightSemibold },
        { value: '700', label: f.weightBold },
    ],
};

export const FIELDS_BY_TYPE: Record<string, PropertyFieldDef[]> = {
    'mj-body': [color('background-color', text.style.backgroundColor), field('width', text.style.contentWidth, '600px')],
    'mj-section': [
        color('background-color', text.style.backgroundColor),
        url('background-url', f.backgroundImage),
        padding,
    ],
    'mj-column': [
        color('background-color', text.style.backgroundColor),
        field('width', f.width, '50%'),
        {
            name: 'vertical-align',
            label: f.verticalAlign,
            kind: 'select',
            options: [
                { value: 'top', label: f.top },
                { value: 'middle', label: f.middle },
                { value: 'bottom', label: f.bottom },
            ],
        },
        padding,
    ],
    'mj-text': [
        color('color', text.style.textColor),
        field('font-size', f.fontSize, '14px'),
        fontWeight,
        field('line-height', f.lineHeight, '1.5'),
        align,
        padding,
    ],
    'mj-button': [
        url('href', text.button.link),
        color('background-color', text.style.buttonBackground),
        color('color', text.style.buttonText),
        field('font-size', f.fontSize, '14px'),
        field('border-radius', text.style.borderRadius, '4px'),
        field('width', f.width),
        align,
        padding,
    ],
    'mj-image': [
        url('src', text.image.url),
        field('alt', text.image.alt),
        url('href', text.image.link),
        field('width', f.width, '600px'),
        align,
        padding,
    ],
    'mj-divider': [
        color('border-color', f.color),
        field('border-width', f.borderWidth, '1px'),
        {
            name: 'border-style',
            label: f.borderStyle,
            kind: 'select',
            options: [
                { value: 'solid', label: f.solid },
                { value: 'dashed', label: f.dashed },
                { value: 'dotted', label: f.dotted },
            ],
        },
        field('width', f.width, '100%'),
        padding,
    ],
    'mj-spacer': [field('height', f.height, '24px')],
    'mj-social': [
        field('icon-size', f.iconSize, '24px'),
        {
            name: 'mode',
            label: f.mode,
            kind: 'select',
            options: [
                { value: 'horizontal', label: f.horizontal },
                { value: 'vertical', label: f.vertical },
            ],
        },
        align,
        padding,
    ],
    'mj-social-element': [
        {
            name: 'name',
            label: f.network,
            kind: 'select',
            options: [
                ...['facebook', 'instagram', 'linkedin', 'x', 'youtube', 'pinterest'].map((value) => ({
                    value,
                    label: value.charAt(0).toUpperCase() + value.slice(1),
                })),
                { value: 'web', label: f.website },
            ],
        },
        url('href', text.button.link),
    ],
    'mj-navbar': [
        align,
        {
            name: 'hamburger',
            label: f.hamburger,
            kind: 'select',
            options: [{ value: 'hamburger', label: f.hamburger }],
        },
    ],
    'mj-navbar-link': [url('href', text.button.link), color('color', f.color), padding],
    'mj-raw': [],
};

/** Composed blocks: block-specific field lists (doc 46 §7 "Mapping blocks"). */
export const FIELDS_BY_BLOCK: Partial<Record<EmailBlockType, PropertyFieldDef[]>> = {
    video: [url('href', f.videoUrl), url('src', f.thumbnail), field('alt', text.image.alt), field('width', f.width, '560px'), align],
    logo: [url('src', text.image.url), field('alt', text.image.alt), url('href', text.image.link), field('width', f.width, '120px'), align],
};

const TITLE_SIZES: Record<string, string> = { h1: '28px', h2: '22px', h3: '18px' };

export const PRESETS_BY_BLOCK: Partial<Record<EmailBlockType, PropertyPreset>> = {
    title: {
        label: f.titleLevel,
        options: (['h1', 'h2', 'h3'] as const).map((level) => ({
            value: level,
            label: text.titleLevels[level],
            attributes: { 'font-size': TITLE_SIZES[level], 'font-weight': '700', 'line-height': '1.2' },
        })),
        current: (attributes) =>
            Object.entries(TITLE_SIZES).find(([, size]) => size === attributes['font-size'])?.[0] ?? '',
    },
};

export function fieldsFor(type: string, block: EmailBlockType | null): PropertyFieldDef[] {
    return (block ? FIELDS_BY_BLOCK[block] : undefined) ?? FIELDS_BY_TYPE[type] ?? [];
}
