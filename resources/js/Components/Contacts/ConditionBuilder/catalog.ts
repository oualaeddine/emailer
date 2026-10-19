/**
 * docs/45-brevo-api-contracts.md §45.5.2 — field catalog (static fields +
 * GET /contact-attributes rows) and the operator set of every value type.
 */
import { fr } from '@/Lib/i18n/fr';
import type { ContactAttribute } from '@/Lib/types/attributes';
import type { ContactSource, ContactStatus } from '@/Lib/types/contacts';
import type {
    ConditionOperator,
    ConditionValueType,
    FieldCatalog,
    FieldCategory,
    FieldDefinition,
    FieldKey,
    OperatorsByType,
    StaticFieldKey,
} from '@/Lib/types/segments';

/** Display order of the field picker groups. */
export const FIELD_CATEGORIES: FieldCategory[] = ['attribute', 'contact', 'list', 'engagement', 'date', 'pagejaunes'];

export const OPERATORS_BY_TYPE: { [K in keyof OperatorsByType]: OperatorsByType[K][] } = {
    text: ['equals', 'not_equals', 'contains', 'not_contains', 'starts_with', 'ends_with', 'in', 'not_in', 'is_empty', 'is_not_empty'],
    number: [
        'equals',
        'not_equals',
        'greater_than',
        'greater_or_equal',
        'less_than',
        'less_or_equal',
        'between',
        'is_empty',
        'is_not_empty',
    ],
    date: [
        'on',
        'before',
        'after',
        'between',
        'in_last_days',
        'more_than_days_ago',
        'in_next_days',
        'anniversary_today',
        'is_empty',
        'is_not_empty',
    ],
    boolean: ['is_true', 'is_false', 'is_empty'],
    category: ['in', 'not_in', 'is_empty', 'is_not_empty'],
    multiple_choice: ['contains_any', 'contains_all', 'not_contains_any', 'is_empty', 'is_not_empty'],
    user: ['in', 'not_in', 'is_empty', 'is_not_empty'],
    tags: ['contains_any', 'contains_all', 'not_contains_any', 'is_empty', 'is_not_empty'],
    list: ['in', 'not_in'],
    engagement: ['has', 'has_not'],
};

const CONTACT_STATUSES: ContactStatus[] = ['active', 'suppressed', 'invalid'];
const CONTACT_SOURCES: ContactSource[] = ['manual', 'csv_import', 'excel_import', 'pagejaunes', 'internal_contact', 'form', 'api'];

type StaticFieldSpec = { key: StaticFieldKey; category: FieldCategory; type: ConditionValueType };

const STATIC_FIELDS: StaticFieldSpec[] = [
    { key: 'contact.tags', category: 'contact', type: 'tags' },
    { key: 'contact.status', category: 'contact', type: 'category' },
    { key: 'contact.source', category: 'contact', type: 'category' },
    { key: 'contact.blocklisted', category: 'contact', type: 'boolean' },
    { key: 'contact.engagement_score', category: 'contact', type: 'number' },
    { key: 'list', category: 'list', type: 'list' },
    { key: 'engagement.sent', category: 'engagement', type: 'engagement' },
    { key: 'engagement.delivered', category: 'engagement', type: 'engagement' },
    { key: 'engagement.opened', category: 'engagement', type: 'engagement' },
    { key: 'engagement.clicked', category: 'engagement', type: 'engagement' },
    { key: 'engagement.soft_bounced', category: 'engagement', type: 'engagement' },
    { key: 'engagement.hard_bounced', category: 'engagement', type: 'engagement' },
    { key: 'engagement.unsubscribed', category: 'engagement', type: 'engagement' },
    { key: 'engagement.complained', category: 'engagement', type: 'engagement' },
    { key: 'contact.created_at', category: 'date', type: 'date' },
    { key: 'contact.updated_at', category: 'date', type: 'date' },
    { key: 'contact.last_contacted_at', category: 'date', type: 'date' },
    { key: 'contact.last_opened_at', category: 'date', type: 'date' },
    { key: 'contact.last_clicked_at', category: 'date', type: 'date' },
    { key: 'contact.opt_in_at', category: 'date', type: 'date' },
    { key: 'contact.blocklisted_at', category: 'date', type: 'date' },
    { key: 'pagejaunes.sector', category: 'pagejaunes', type: 'text' },
    { key: 'pagejaunes.wilaya', category: 'pagejaunes', type: 'text' },
    { key: 'pagejaunes.locality', category: 'pagejaunes', type: 'text' },
    { key: 'pagejaunes.legal_form', category: 'pagejaunes', type: 'text' },
    { key: 'pagejaunes.is_exporter', category: 'pagejaunes', type: 'boolean' },
    { key: 'pagejaunes.is_importer', category: 'pagejaunes', type: 'boolean' },
    { key: 'pagejaunes.is_producer', category: 'pagejaunes', type: 'boolean' },
    { key: 'pagejaunes.is_distributor', category: 'pagejaunes', type: 'boolean' },
];

function staticOptions(key: StaticFieldKey): FieldDefinition['options'] {
    if (key === 'contact.status') {
        return CONTACT_STATUSES.map((value) => ({ value, label: fr.contacts.statusLabels[value] }));
    }
    if (key === 'contact.source') {
        return CONTACT_SOURCES.map((value) => ({ value, label: fr.contacts.sourceLabels[value] }));
    }

    return undefined;
}

/** The static (non-attribute) fields of §45.5.2 with their French labels. */
export function staticFieldDefinitions(): FieldDefinition[] {
    return STATIC_FIELDS.map(({ key, category, type }) => {
        const options = staticOptions(key);

        return {
            key,
            label: fr.conditionBuilder.fields[key],
            category,
            type,
            ...(options ? { options } : {}),
        };
    });
}

/** One `attribute.<NAME>` field per GET /contact-attributes row. */
export function attributeFieldDefinition(attribute: ContactAttribute): FieldDefinition {
    const hasOptions = attribute.type === 'category' || attribute.type === 'multiple_choice';

    return {
        key: `attribute.${attribute.name}`,
        label: attribute.label || attribute.name,
        category: 'attribute',
        type: attribute.type,
        ...(hasOptions ? { options: attribute.options.map((o) => ({ value: o.value, label: o.label })) } : {}),
    };
}

export interface BuildFieldCatalogOptions {
    /** Keep only these categories (e.g. automations hide "pagejaunes"). Default: all. */
    categories?: FieldCategory[];
}

/**
 * §45.5.3 — GET /contact-attributes rows (system first, as returned) followed
 * by the static contact / list / engagement / date / PagesJaunes fields.
 */
export function buildFieldCatalog(attributes: ContactAttribute[], options: BuildFieldCatalogOptions = {}): FieldCatalog {
    const fields = [...attributes.map(attributeFieldDefinition), ...staticFieldDefinitions()];
    const categories = options.categories;

    return { fields: categories ? fields.filter((f) => categories.includes(f.category)) : fields };
}

export function findField(catalog: FieldCatalog, key: FieldKey): FieldDefinition | undefined {
    return catalog.fields.find((f) => f.key === key);
}

export function operatorsFor(field: FieldDefinition): ConditionOperator[] {
    return field.operators && field.operators.length > 0 ? field.operators : OPERATORS_BY_TYPE[field.type];
}

/** French label of an operator; list membership has its own wording. */
export function operatorLabel(type: ConditionValueType | undefined, operator: ConditionOperator): string {
    if (type === 'list' && (operator === 'in' || operator === 'not_in')) {
        return fr.conditionBuilder.listOperators[operator];
    }

    return fr.conditionBuilder.operators[operator] ?? operator;
}
