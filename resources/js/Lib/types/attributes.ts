/**
 * docs/45-brevo-api-contracts.md §45.5.1 — WP-50 Contact attributes.
 * /api/v1/contact-attributes, /api/v1/contact-attributes/merge-tags.
 */
import type { IsoDateTime, Uuid } from '@/Lib/types/common';

/** Brevo's 7 attribute types. Labels: fr.attributes.types. */
export type ContactAttributeType = 'text' | 'number' | 'date' | 'boolean' | 'category' | 'multiple_choice' | 'user';

export type ContactAttributeStorage = 'column' | 'custom_fields';

/** Seeded, read-only attributes. */
export type SystemAttributeName = 'EMAIL' | 'PRENOM' | 'NOM' | 'SOCIETE' | 'SMS' | 'EXT_ID';

export interface ContactAttributeOption {
    value: string;
    label: string;
}

export interface ContactAttribute {
    id: Uuid;
    /** UPPERCASE, ^[A-Z][A-Z0-9_]{0,49}$ */
    name: string;
    label: string;
    type: ContactAttributeType;
    /** Required for category / multiple_choice, [] otherwise. */
    options: ContactAttributeOption[];
    is_system: boolean;
    storage: ContactAttributeStorage;
    /** Recipients column for system attributes stored in a column (e.g. "first_name"). */
    column: string | null;
    merge_tag: string;
    /** Only with include_usage=1. */
    usage_count: number | null;
    created_at: IsoDateTime;
    updated_at: IsoDateTime;
}

export interface ContactAttributeInput {
    name: string;
    label?: string;
    type: ContactAttributeType;
    options?: ContactAttributeOption[];
}

/** Name and type are immutable. */
export interface ContactAttributeUpdateInput {
    label?: string;
    options?: ContactAttributeOption[];
}

/**
 * Encoded attribute value: text → string; number → number; date → "YYYY-MM-DD";
 * boolean → boolean; category → option value; multiple_choice → option values;
 * user → user uuid.
 */
export type ContactAttributeValue = string | number | boolean | string[] | null;

/** `{ "VILLE": "alger", … }` keyed by attribute name. */
export type ContactAttributeValues = Record<string, ContactAttributeValue>;

export interface AttributeUsageRef {
    type: 'segment' | 'form' | 'automation' | 'view';
    id: Uuid;
    name: string;
}

/** Labels: fr.attributes.mergeTagGroups. */
export type MergeTagGroup = 'contact' | 'links' | 'campaign';

export interface MergeTag {
    tag: string;
    label: string;
    group: MergeTagGroup;
    attribute: string | null;
}
