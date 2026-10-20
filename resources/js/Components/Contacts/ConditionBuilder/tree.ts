/**
 * docs/45-brevo-api-contracts.md §45.5.2 — rule-tree (version 2) helpers:
 * value shapes per operator, completeness checks and immutable updates.
 */
import type { IsoDate } from '@/Lib/types/common';
import type {
    ConditionOperator,
    ConditionValue,
    ConditionValueType,
    EngagementValue,
    FieldCatalog,
    FieldDefinition,
    RuleCondition,
    RuleGroup,
    RuleLogicalOperator,
    RuleTree,
} from '@/Lib/types/segments';
import { findField, operatorsFor } from './catalog';

export const DEFAULT_MAX_CONDITIONS = 100;
export const MAX_LIST_VALUES = 500;
export const MAX_ENGAGEMENT_CAMPAIGNS = 50;
export const MIN_DAYS = 1;
export const MAX_DAYS = 3650;

/** The editor a (type, operator) pair needs; `none` = value is always `null`. */
export type ValueShape =
    | 'none'
    | 'text'
    | 'text_list'
    | 'number'
    | 'number_range'
    | 'date'
    | 'date_range'
    | 'days'
    | 'options'
    | 'users'
    | 'tags'
    | 'lists'
    | 'engagement';

const NULL_OPERATORS: ConditionOperator[] = ['is_empty', 'is_not_empty', 'is_true', 'is_false', 'anniversary_today'];

export function valueShape(type: ConditionValueType | undefined, operator: ConditionOperator): ValueShape {
    if (NULL_OPERATORS.includes(operator)) {
        return 'none';
    }
    switch (type) {
        case 'text':
            return operator === 'in' || operator === 'not_in' ? 'text_list' : 'text';
        case 'number':
            return operator === 'between' ? 'number_range' : 'number';
        case 'date':
            if (operator === 'between') return 'date_range';
            if (operator === 'in_last_days' || operator === 'more_than_days_ago' || operator === 'in_next_days') return 'days';
            return 'date';
        case 'boolean':
            return 'none';
        case 'category':
        case 'multiple_choice':
            return 'options';
        case 'user':
            return 'users';
        case 'tags':
            return 'tags';
        case 'list':
            return 'lists';
        case 'engagement':
            return 'engagement';
        default:
            return 'text';
    }
}

export function defaultEngagementValue(): EngagementValue {
    return {
        campaign_ids: null,
        within_days: 30,
        from: null,
        to: null,
        link_url: null,
        min_count: null,
        automation_step_id: null,
    };
}

export function defaultValueFor(shape: ValueShape): ConditionValue {
    return shape === 'engagement' ? defaultEngagementValue() : null;
}

export function emptyRuleTree(operator: RuleLogicalOperator = 'and'): RuleTree {
    return { version: 2, type: 'group', operator, children: [] };
}

export function createCondition(field: FieldDefinition): RuleCondition {
    const operator = operatorsFor(field)[0];

    return {
        type: 'condition',
        field: field.key,
        operator,
        value: defaultValueFor(valueShape(field.type, operator)),
    };
}

export function createGroup(field: FieldDefinition, operator: RuleLogicalOperator = 'and'): RuleGroup {
    return { type: 'group', operator, children: [createCondition(field)] };
}

/** Switching field resets operator and value to the new field's defaults. */
export function withField(condition: RuleCondition, field: FieldDefinition): RuleCondition {
    return { ...createCondition(field), type: condition.type };
}

/** Switching operator keeps the value when its shape is unchanged. */
export function withOperator(condition: RuleCondition, field: FieldDefinition | undefined, operator: ConditionOperator): RuleCondition {
    const before = valueShape(field?.type, condition.operator);
    const after = valueShape(field?.type, operator);

    return { ...condition, operator, value: before === after ? condition.value : defaultValueFor(after) };
}

export function countConditions(tree: RuleTree | null): number {
    if (!tree) return 0;

    return tree.children.reduce((sum, child) => sum + (child.type === 'group' ? child.children.length : 1), 0);
}

// ---------------------------------------------------------------------------
// Completeness
// ---------------------------------------------------------------------------

const ISO_DATE = /^\d{4}-\d{2}-\d{2}$/;

export function isIsoDate(value: unknown): value is IsoDate {
    return typeof value === 'string' && ISO_DATE.test(value) && !Number.isNaN(Date.parse(value));
}

function isFiniteNumber(value: unknown): value is number {
    return typeof value === 'number' && Number.isFinite(value);
}

function isDays(value: unknown): boolean {
    return isFiniteNumber(value) && Number.isInteger(value) && value >= MIN_DAYS && value <= MAX_DAYS;
}

function isNonEmptyList(value: unknown, itemCheck: (item: unknown) => boolean): boolean {
    return Array.isArray(value) && value.length > 0 && value.length <= MAX_LIST_VALUES && value.every(itemCheck);
}

const isNonEmptyString = (v: unknown) => typeof v === 'string' && v.trim() !== '';

export function isEngagementValueComplete(value: unknown): boolean {
    if (value === null || typeof value !== 'object' || Array.isArray(value)) return false;
    const v = value as Partial<EngagementValue>;
    if (
        v.campaign_ids != null &&
        !(Array.isArray(v.campaign_ids) && v.campaign_ids.length >= 1 && v.campaign_ids.length <= MAX_ENGAGEMENT_CAMPAIGNS)
    ) {
        return false;
    }
    if (v.within_days != null && !isDays(v.within_days)) return false;
    const hasFrom = v.from != null;
    const hasTo = v.to != null;
    if (hasFrom !== hasTo) return false;
    if (hasFrom && (!isIsoDate(v.from) || !isIsoDate(v.to) || (v.from as string) > (v.to as string))) return false;
    if (v.min_count != null && !(isFiniteNumber(v.min_count) && Number.isInteger(v.min_count) && v.min_count >= 1)) return false;

    return true;
}

export function isValueComplete(shape: ValueShape, value: ConditionValue): boolean {
    switch (shape) {
        case 'none':
            return value === null;
        case 'text':
            return isNonEmptyString(value);
        case 'text_list':
        case 'options':
        case 'users':
        case 'lists':
            return isNonEmptyList(value, isNonEmptyString);
        case 'tags':
            return isNonEmptyList(value, isFiniteNumber);
        case 'number':
            return isFiniteNumber(value);
        case 'number_range':
            return (
                Array.isArray(value) && value.length === 2 && isFiniteNumber(value[0]) && isFiniteNumber(value[1]) && value[0] <= value[1]
            );
        case 'date':
            return isIsoDate(value);
        case 'date_range':
            return Array.isArray(value) && value.length === 2 && isIsoDate(value[0]) && isIsoDate(value[1]) && value[0] <= value[1];
        case 'days':
            return isDays(value);
        case 'engagement':
            return isEngagementValueComplete(value);
    }
}

/** A condition is complete when its field is known (when a catalog is given), its operator allowed and its value valid. */
export function isConditionComplete(condition: RuleCondition, catalog?: FieldCatalog): boolean {
    const field = catalog ? findField(catalog, condition.field) : undefined;
    if (catalog && !field) return false;
    if (field && !operatorsFor(field).includes(condition.operator)) return false;
    const type = field?.type ?? inferType(condition);

    return isValueComplete(valueShape(type, condition.operator), condition.value);
}

/** Best-effort type for a condition when no catalog is available. */
function inferType(condition: RuleCondition): ConditionValueType | undefined {
    const key = condition.field;
    if (key === 'list') return 'list';
    if (key === 'contact.tags') return 'tags';
    if (key.startsWith('engagement.')) return 'engagement';
    if (key === 'contact.engagement_score') return 'number';
    if (key === 'contact.status' || key === 'contact.source') return 'category';
    if (key === 'contact.blocklisted' || key.startsWith('pagejaunes.is_')) return 'boolean';
    if (key.startsWith('contact.') && key.endsWith('_at')) return 'date';
    if (key.startsWith('pagejaunes.')) return 'text';
    if (condition.operator === 'contains_any' || condition.operator === 'contains_all' || condition.operator === 'not_contains_any') {
        return 'multiple_choice';
    }

    return undefined;
}

/**
 * §45.5.3 — `true` when the tree can be saved / counted: every condition is
 * complete, no group is empty, depth ≤ 2 and the condition count is within
 * `maxConditions`. An empty root (`children: []`) is complete (= all contacts).
 */
export function isRuleTreeComplete(tree: RuleTree | null, catalog?: FieldCatalog, maxConditions: number = DEFAULT_MAX_CONDITIONS): boolean {
    if (!tree) return true;
    if (countConditions(tree) > maxConditions) return false;

    return tree.children.every((child) =>
        child.type === 'group'
            ? child.children.length > 0 && child.children.every((c) => c.type === 'condition' && isConditionComplete(c, catalog))
            : isConditionComplete(child, catalog),
    );
}

// ---------------------------------------------------------------------------
// Dot paths (server errors: "children.1.children.0.value")
// ---------------------------------------------------------------------------

export function nodePath(rootIndex: number, childIndex?: number): string {
    return childIndex === undefined ? `children.${rootIndex}` : `children.${rootIndex}.children.${childIndex}`;
}

/** Errors addressed to `path` itself or to one of its direct properties (`operator`, `value`, `field`…). */
export function errorsAt(errors: Record<string, string> | undefined, path: string, deep = true): string[] {
    if (!errors) return [];

    return Object.entries(errors)
        .filter(([key]) => {
            if (key === path) return true;
            if (!key.startsWith(`${path}.`)) return false;

            return deep || !key.slice(path.length + 1).startsWith('children');
        })
        .map(([, message]) => message);
}

// ---------------------------------------------------------------------------
// Stable React keys (the grammar carries no ids)
// ---------------------------------------------------------------------------

const nodeKeys = new WeakMap<object, string>();
let nodeKeySeq = 0;

/** Stable key of a tree node object; assigned on first sight. */
export function nodeKey(node: object): string {
    let key = nodeKeys.get(node);
    if (!key) {
        key = `n${++nodeKeySeq}`;
        nodeKeys.set(node, key);
    }

    return key;
}

/** Carries the key of `from` over to its immutable replacement `to`. */
export function inheritKey<T extends object>(from: object, to: T): T {
    nodeKeys.set(to, nodeKey(from));

    return to;
}
