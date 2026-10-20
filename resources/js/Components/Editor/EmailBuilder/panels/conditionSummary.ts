/**
 * WP-61 — "Afficher si : …" one-line summary of a dynamic-content rule tree
 * (docs/45 §45.5.2 grammar), with the ConditionBuilder's French field and
 * operator labels.
 */
import { findField, operatorLabel } from '@/Components/Contacts/ConditionBuilder';
import type { FieldCatalog, FieldDefinition, RuleCondition, RuleGroup, RuleLogicalOperator, RuleTree } from '@/Lib/types/segments';
import { text } from '../strings';

function valueText(value: unknown, field: FieldDefinition | undefined): string {
    if (value === null || value === undefined || value === '') {
        return '';
    }
    const label = (item: unknown) => {
        const option = field?.options?.find((o) => String(o.value) === String(item));

        return option ? option.label : String(item);
    };
    if (Array.isArray(value)) {
        return value.map(label).join(', ');
    }
    if (typeof value === 'boolean') {
        return value ? text.condition.booleanValues.true : text.condition.booleanValues.false;
    }
    if (typeof value === 'object') {
        return '';
    }

    return label(value);
}

function conditionText(condition: RuleCondition, catalog: FieldCatalog): string {
    const field = findField(catalog, condition.field);

    return [field?.label ?? condition.field, operatorLabel(field?.type, condition.operator), valueText(condition.value, field)]
        .filter((part) => part !== '')
        .join(' ');
}

function joiner(operator: RuleLogicalOperator): string {
    return ` ${operator === 'and' ? text.condition.and : text.condition.or} `;
}

function nodeText(node: RuleGroup | RuleCondition, catalog: FieldCatalog): string {
    if (node.type === 'group') {
        const inner = node.children.map((child) => conditionText(child, catalog)).join(joiner(node.operator));

        return node.children.length > 1 ? `(${inner})` : inner;
    }

    return conditionText(node, catalog);
}

export function summariseCondition(tree: RuleTree | null, catalog: FieldCatalog): string {
    if (!tree || tree.children.length === 0) {
        return text.condition.empty;
    }

    return tree.children.map((node) => nodeText(node, catalog)).join(joiner(tree.operator));
}
