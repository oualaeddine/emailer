import { fr } from '@/Lib/i18n/fr';
import type { ContactAttribute, ContactAttributeType } from '@/Lib/types/attributes';
import type { ContactColumn, ContactColumnKey } from '@/Lib/types/contacts';
import type {
    ConditionOperator,
    FieldCatalog,
    FieldDefinition,
    RuleCondition,
    RuleGroup,
    RuleTree,
    StaticFieldKey,
} from '@/Lib/types/segments';

/**
 * docs/43-brevo-parity-plan.md §43.4.6 / docs/45 §45.5.4 — column catalog of
 * the v2 Contacts table ("Personnaliser les colonnes") and the field catalog
 * handed to the shared ConditionBuilder (§45.5.3).
 */

export type ContactColumnType = ContactAttributeType | 'lists' | 'tags';

export interface ContactColumnDescriptor {
    key: ContactColumnKey;
    label: string;
    type: ContactColumnType;
    /** §45.5.4 — sort accepts any column of type text / number / date. */
    sortable: boolean;
    /** Set for `attribute.<NAME>` columns. */
    attribute?: ContactAttribute;
    /** Category labels for status / source and category attributes. */
    options?: Record<string, string>;
}

/** The identifier column: always shown first, cannot be hidden. */
export const PINNED_COLUMN_KEY: ContactColumnKey = 'attribute.EMAIL';

export const DEFAULT_COLUMN_WIDTH = 160;

export const DEFAULT_CONTACT_COLUMNS: ContactColumn[] = [
    { key: 'attribute.EMAIL', width: 260 },
    { key: 'attribute.PRENOM', width: DEFAULT_COLUMN_WIDTH },
    { key: 'attribute.NOM', width: DEFAULT_COLUMN_WIDTH },
    { key: 'attribute.SOCIETE', width: 200 },
    { key: 'contact.status', width: 120 },
    { key: 'lists', width: 200 },
    { key: 'contact.created_at', width: 160 },
];

export const DEFAULT_CONTACT_SORT = '-contact.created_at';

/** Labels of the system attributes stored in recipients columns (fallback when GET /contact-attributes is unavailable). */
const SYSTEM_ATTRIBUTE_LABELS: Record<string, string> = {
    EMAIL: fr.contacts.columns.email,
    PRENOM: fr.contacts.columns.first_name,
    NOM: fr.contacts.columns.last_name,
    SOCIETE: fr.contacts.columns.company_name,
    EXT_ID: fr.contacts.columns.ext_id,
};

const SYSTEM_ATTRIBUTE_TYPES: Record<string, ContactAttributeType> = {
    EMAIL: 'text',
    PRENOM: 'text',
    NOM: 'text',
    SOCIETE: 'text',
    EXT_ID: 'text',
};

type StaticColumnKey = Extract<
    ContactColumnKey,
    | 'contact.status'
    | 'contact.source'
    | 'contact.blocklisted'
    | 'contact.engagement_score'
    | 'contact.created_at'
    | 'contact.updated_at'
    | 'contact.last_opened_at'
    | 'contact.last_clicked_at'
    | 'contact.opt_in_at'
    | 'contact.blocklisted_at'
    | 'lists'
    | 'tags'
>;

const STATIC_COLUMNS: Record<StaticColumnKey, { label: string; type: ContactColumnType; options?: Record<string, string> }> = {
    'contact.status': { label: fr.contacts.columns.status, type: 'category', options: fr.contacts.statusLabels },
    'contact.source': { label: fr.contacts.columns.source, type: 'category', options: fr.contacts.sourceLabels },
    'contact.blocklisted': { label: fr.contacts.columns.blocklisted, type: 'boolean' },
    'contact.engagement_score': { label: fr.contacts.columns.engagement_score, type: 'number' },
    'contact.created_at': { label: fr.contacts.columns.created_at, type: 'date' },
    'contact.updated_at': { label: fr.contacts.columns.updated_at, type: 'date' },
    'contact.last_opened_at': { label: fr.contacts.columns.last_opened_at, type: 'date' },
    'contact.last_clicked_at': { label: fr.contacts.columns.last_clicked_at, type: 'date' },
    'contact.opt_in_at': { label: fr.conditionBuilder.fields['contact.opt_in_at'], type: 'date' },
    'contact.blocklisted_at': { label: fr.conditionBuilder.fields['contact.blocklisted_at'], type: 'date' },
    lists: { label: fr.contacts.columns.lists, type: 'lists' },
    tags: { label: fr.contacts.columns.tags, type: 'tags' },
};

const SORTABLE_TYPES: ContactColumnType[] = ['text', 'number', 'date'];

function optionsRecord(attribute: ContactAttribute): Record<string, string> | undefined {
    if (attribute.options.length === 0) {
        return undefined;
    }

    return Object.fromEntries(attribute.options.map((option) => [option.value, option.label]));
}

function describeAttribute(attribute: ContactAttribute): ContactColumnDescriptor {
    return {
        key: `attribute.${attribute.name}`,
        label: attribute.label || SYSTEM_ATTRIBUTE_LABELS[attribute.name] || attribute.name,
        type: attribute.type,
        sortable: SORTABLE_TYPES.includes(attribute.type),
        attribute,
        options: optionsRecord(attribute),
    };
}

/** Every column the user may display: attributes (system first, as returned) then the static contact columns. */
export function buildColumnCatalog(attributes: ContactAttribute[]): ContactColumnDescriptor[] {
    const fromAttributes = attributes.map(describeAttribute);
    const seen = new Set(fromAttributes.map((column) => column.key));

    // System attributes always exist server-side, even before the attribute list has loaded.
    const systemFallbacks = Object.keys(SYSTEM_ATTRIBUTE_LABELS)
        .map((name) => `attribute.${name}` as ContactColumnKey)
        .filter((key) => !seen.has(key))
        .map((key) => describeColumn(key, new Map()));

    const statics = (Object.keys(STATIC_COLUMNS) as StaticColumnKey[]).map((key) => describeColumn(key, new Map()));

    return [...fromAttributes, ...systemFallbacks, ...statics];
}

export function indexColumnCatalog(catalog: ContactColumnDescriptor[]): Map<ContactColumnKey, ContactColumnDescriptor> {
    return new Map(catalog.map((column) => [column.key, column]));
}

/** Descriptor for any key, even one whose attribute was deleted since the view was saved. */
export function describeColumn(
    key: ContactColumnKey,
    catalog: Map<ContactColumnKey, ContactColumnDescriptor>,
): ContactColumnDescriptor {
    const known = catalog.get(key);
    if (known) {
        return known;
    }

    if (key in STATIC_COLUMNS) {
        const definition = STATIC_COLUMNS[key as StaticColumnKey];

        return { key, ...definition, sortable: SORTABLE_TYPES.includes(definition.type) };
    }

    if (key.startsWith('attribute.')) {
        const name = key.slice('attribute.'.length);
        const type = SYSTEM_ATTRIBUTE_TYPES[name] ?? 'text';

        return { key, label: SYSTEM_ATTRIBUTE_LABELS[name] ?? name, type, sortable: SORTABLE_TYPES.includes(type) };
    }

    const label = (fr.conditionBuilder.fields as Record<string, string>)[key] ?? key;

    return { key, label, type: 'text', sortable: false };
}

/** Pinned column first, duplicates removed. */
export function normaliseColumns(columns: ContactColumn[] | null | undefined): ContactColumn[] {
    const source = columns && columns.length > 0 ? columns : DEFAULT_CONTACT_COLUMNS;
    const seen = new Set<ContactColumnKey>();
    const result: ContactColumn[] = [];

    const pinned = source.find((column) => column.key === PINNED_COLUMN_KEY) ?? DEFAULT_CONTACT_COLUMNS[0];
    result.push(pinned);
    seen.add(PINNED_COLUMN_KEY);

    for (const column of source) {
        if (!seen.has(column.key)) {
            seen.add(column.key);
            result.push(column);
        }
    }

    return result;
}

// --- Sort -------------------------------------------------------------------

export interface ParsedSort {
    key: ContactColumnKey;
    direction: 'ascending' | 'descending';
}

export function parseSort(sort: string | null | undefined): ParsedSort {
    const value = sort || DEFAULT_CONTACT_SORT;

    return value.startsWith('-')
        ? { key: value.slice(1) as ContactColumnKey, direction: 'descending' }
        : { key: value as ContactColumnKey, direction: 'ascending' };
}

export function formatSort(parsed: ParsedSort): string {
    return parsed.direction === 'descending' ? `-${parsed.key}` : parsed.key;
}

// --- Condition builder field catalog ----------------------------------------

const ENGAGEMENT_FIELDS: StaticFieldKey[] = [
    'engagement.sent',
    'engagement.delivered',
    'engagement.opened',
    'engagement.clicked',
    'engagement.soft_bounced',
    'engagement.hard_bounced',
    'engagement.unsubscribed',
    'engagement.complained',
];

const DATE_FIELDS: StaticFieldKey[] = [
    'contact.created_at',
    'contact.updated_at',
    'contact.last_contacted_at',
    'contact.last_opened_at',
    'contact.last_clicked_at',
    'contact.opt_in_at',
    'contact.blocklisted_at',
];

function toOptions(record: Record<string, string>): { value: string; label: string }[] {
    return Object.entries(record).map(([value, label]) => ({ value, label }));
}

/**
 * Field catalog for the Contacts filter drawer: every row of
 * GET /contact-attributes plus the static contact / list / engagement / date
 * fields (§45.5.2). PagesJaunes fields are left to the shared
 * `buildFieldCatalog` of the ConditionBuilder module.
 */
export function buildContactFieldCatalog(attributes: ContactAttribute[]): FieldCatalog {
    const fieldLabel = (key: StaticFieldKey): string => (fr.conditionBuilder.fields as Record<string, string>)[key] ?? key;

    const fields: FieldDefinition[] = [
        ...attributes.map<FieldDefinition>((attribute) => ({
            key: `attribute.${attribute.name}`,
            label: attribute.label || attribute.name,
            category: 'attribute',
            type: attribute.type,
            options: attribute.options.length > 0 ? attribute.options : undefined,
        })),
        { key: 'contact.status', label: fieldLabel('contact.status'), category: 'contact', type: 'category', options: toOptions(fr.contacts.statusLabels) },
        { key: 'contact.source', label: fieldLabel('contact.source'), category: 'contact', type: 'category', options: toOptions(fr.contacts.sourceLabels) },
        { key: 'contact.blocklisted', label: fieldLabel('contact.blocklisted'), category: 'contact', type: 'boolean' },
        { key: 'contact.engagement_score', label: fieldLabel('contact.engagement_score'), category: 'contact', type: 'number' },
        { key: 'contact.tags', label: fieldLabel('contact.tags'), category: 'contact', type: 'tags' },
        { key: 'list', label: fieldLabel('list'), category: 'list', type: 'list' },
        ...ENGAGEMENT_FIELDS.map<FieldDefinition>((key) => ({ key, label: fieldLabel(key), category: 'engagement', type: 'engagement' })),
        ...DATE_FIELDS.map<FieldDefinition>((key) => ({ key, label: fieldLabel(key), category: 'date', type: 'date' })),
    ];

    return { fields };
}

// --- Rule tree helpers --------------------------------------------------------

const NO_VALUE_OPERATORS: ConditionOperator[] = ['is_empty', 'is_not_empty', 'is_true', 'is_false', 'anniversary_today'];

function conditionsOf(tree: RuleTree): RuleCondition[] {
    return tree.children.flatMap((child: RuleGroup | RuleCondition) => (child.type === 'group' ? child.children : [child]));
}

export function countConditions(tree: RuleTree | null): number {
    return tree ? conditionsOf(tree).length : 0;
}

function isConditionComplete(condition: RuleCondition): boolean {
    if (!condition.field || !condition.operator) {
        return false;
    }
    if (NO_VALUE_OPERATORS.includes(condition.operator)) {
        return true;
    }
    const value = condition.value;
    if (value === null || value === undefined) {
        return false;
    }
    if (typeof value === 'string') {
        return value.trim() !== '';
    }
    if (Array.isArray(value)) {
        return value.length > 0 && value.every((item) => item !== null && item !== '');
    }

    return true;
}

/** Same rule as the ConditionBuilder's `isRuleTreeComplete`: every row has a field, operator and (when needed) a value. */
export function isFilterTreeComplete(tree: RuleTree | null): boolean {
    return tree !== null && conditionsOf(tree).every(isConditionComplete);
}

/** `null` when the tree has no condition (no filter). */
export function normaliseFilterTree(tree: RuleTree | null): RuleTree | null {
    return tree && countConditions(tree) > 0 ? tree : null;
}

/** Replaces each `%d` / `%s` placeholder of a fr.ts string in order. */
export function formatText(template: string, ...args: Array<string | number>): string {
    let index = 0;

    return template.replace(/%[ds]/g, (placeholder) => {
        const arg = args[index++];
        if (arg === undefined) {
            return placeholder;
        }

        return typeof arg === 'number' ? arg.toLocaleString('fr-FR') : arg;
    });
}
