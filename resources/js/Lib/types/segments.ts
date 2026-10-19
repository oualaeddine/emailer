/**
 * docs/45-brevo-api-contracts.md §45.5.2 (WP-54 Segments v2 + rule-tree
 * grammar version 2) and §45.5.3 (ConditionBuilder props contract —
 * WP-54U builds, WP-51 / WP-91U embed).
 */
import type { ReactNode } from 'react';
import type { ContactAttributeType } from '@/Lib/types/attributes';
import type { IsoDate, IsoDateTime, UserRef, Uuid } from '@/Lib/types/common';

// ---------------------------------------------------------------------------
// Rule tree (version 2)
// ---------------------------------------------------------------------------

export type RuleLogicalOperator = 'and' | 'or';

/** UI groups of the field picker. Labels: fr.conditionBuilder.categories. */
export type FieldCategory = 'attribute' | 'contact' | 'list' | 'engagement' | 'date' | 'pagejaunes';

export type EngagementFieldKey =
    | 'engagement.sent'
    | 'engagement.delivered'
    | 'engagement.opened'
    | 'engagement.clicked'
    | 'engagement.soft_bounced'
    | 'engagement.hard_bounced'
    | 'engagement.unsubscribed'
    | 'engagement.complained';

export type ContactDateFieldKey =
    | 'contact.created_at'
    | 'contact.updated_at'
    | 'contact.last_contacted_at'
    | 'contact.last_opened_at'
    | 'contact.last_clicked_at'
    | 'contact.opt_in_at'
    | 'contact.blocklisted_at';

export type PageJaunesFieldKey =
    | 'pagejaunes.sector'
    | 'pagejaunes.wilaya'
    | 'pagejaunes.locality'
    | 'pagejaunes.legal_form'
    | 'pagejaunes.is_exporter'
    | 'pagejaunes.is_importer'
    | 'pagejaunes.is_producer'
    | 'pagejaunes.is_distributor';

/** Every static (non-attribute) field of §45.5.2. */
export type StaticFieldKey =
    | 'contact.tags'
    | 'contact.status'
    | 'contact.source'
    | 'contact.blocklisted'
    | 'contact.engagement_score'
    | 'list'
    | EngagementFieldKey
    | ContactDateFieldKey
    | PageJaunesFieldKey;

/** `attribute.<NAME>` for every row of GET /contact-attributes. */
export type AttributeFieldKey = `attribute.${string}`;

export type FieldKey = StaticFieldKey | AttributeFieldKey;

/** Operator sets (§45.5.2 table). */
export type ConditionValueType = ContactAttributeType | 'tags' | 'list' | 'engagement';

export type TextOperator =
    | 'equals'
    | 'not_equals'
    | 'contains'
    | 'not_contains'
    | 'starts_with'
    | 'ends_with'
    | 'in'
    | 'not_in'
    | 'is_empty'
    | 'is_not_empty';

export type NumberOperator =
    | 'equals'
    | 'not_equals'
    | 'greater_than'
    | 'greater_or_equal'
    | 'less_than'
    | 'less_or_equal'
    | 'between'
    | 'is_empty'
    | 'is_not_empty';

export type DateOperator =
    | 'on'
    | 'before'
    | 'after'
    | 'between'
    | 'in_last_days'
    | 'more_than_days_ago'
    | 'in_next_days'
    | 'anniversary_today'
    | 'is_empty'
    | 'is_not_empty';

export type BooleanOperator = 'is_true' | 'is_false' | 'is_empty';

export type CategoryOperator = 'in' | 'not_in' | 'is_empty' | 'is_not_empty';

export type MultipleChoiceOperator = 'contains_any' | 'contains_all' | 'not_contains_any' | 'is_empty' | 'is_not_empty';

export type UserOperator = 'in' | 'not_in' | 'is_empty' | 'is_not_empty';

export type TagsOperator = MultipleChoiceOperator;

export type ListOperator = 'in' | 'not_in';

export type EngagementOperator = 'has' | 'has_not';

/** Union of every operator. Labels: fr.conditionBuilder.operators. */
export type ConditionOperator =
    | TextOperator
    | NumberOperator
    | DateOperator
    | BooleanOperator
    | CategoryOperator
    | MultipleChoiceOperator
    | UserOperator
    | TagsOperator
    | ListOperator
    | EngagementOperator;

/** Operator set per value type. */
export interface OperatorsByType {
    text: TextOperator;
    number: NumberOperator;
    date: DateOperator;
    boolean: BooleanOperator;
    category: CategoryOperator;
    multiple_choice: MultipleChoiceOperator;
    user: UserOperator;
    tags: TagsOperator;
    list: ListOperator;
    engagement: EngagementOperator;
}

export interface EngagementValue {
    /** `null` = "n'importe quelle campagne". */
    campaign_ids: Uuid[] | null;
    /** `null` = ever. */
    within_days: number | null;
    from: IsoDate | null;
    to: IsoDate | null;
    /** engagement.clicked only: exact URL or prefix ending with `*`. */
    link_url: string | null;
    min_count: number | null;
    /** Restrict to e-mails sent by one automation step (WP-91). */
    automation_step_id: Uuid | null;
}

/**
 * Value shapes:
 *  - text: string | string[] (in / not_in)
 *  - number: number | [number, number] (between)
 *  - date: IsoDate | [IsoDate, IsoDate] (between) | number (*_days)
 *  - category / multiple_choice / user / list: string[]
 *  - tags: number[]
 *  - engagement: EngagementValue
 *  - is_empty / is_not_empty / is_true / is_false / anniversary_today: null
 */
export type ConditionValue =
    | string
    | number
    | string[]
    | number[]
    | [number, number]
    | [IsoDate, IsoDate]
    | EngagementValue
    | null;

export interface RuleCondition {
    type: 'condition';
    field: FieldKey;
    operator: ConditionOperator;
    value: ConditionValue;
}

export interface RuleGroup {
    type: 'group';
    operator: RuleLogicalOperator;
    children: RuleCondition[];
}

/** Root of every rule tree (depth ≤ 2, ≤ 100 conditions). */
export interface RuleTree {
    version: 2;
    type: 'group';
    operator: RuleLogicalOperator;
    children: Array<RuleGroup | RuleCondition>;
}

// ---------------------------------------------------------------------------
// Segment resource
// ---------------------------------------------------------------------------

export interface SegmentUsage {
    campaigns: number;
    automations: number;
}

export interface Segment {
    id: Uuid;
    name: string;
    description: string | null;
    folder_id: Uuid | null;
    rules: RuleTree;
    contact_count: number | null;
    count_computed_at: IsoDateTime | null;
    /** Legacy, nullable from v2. */
    recipient_list_id: Uuid | null;
    used_in: SegmentUsage;
    created_by: UserRef | null;
    created_at: IsoDateTime;
    updated_at: IsoDateTime;
}

export interface SegmentInput {
    name: string;
    description?: string | null;
    folder_id?: Uuid | null;
    rules: RuleTree;
}

export interface SegmentCount {
    count: number;
    computed_at: IsoDateTime;
    duration_ms: number;
}

export interface SegmentCountInput {
    rules: RuleTree;
}

export type SegmentSortField = 'name' | 'updated_at' | 'contact_count';

export interface SegmentListQuery {
    q?: string;
    folder_id?: Uuid | 'none';
    sort?: SegmentSortField | `-${SegmentSortField}`;
    page?: number;
    per_page?: number;
}

// ---------------------------------------------------------------------------
// ConditionBuilder props contract (§45.5.3)
// ---------------------------------------------------------------------------

export interface FieldOption {
    value: string | number;
    label: string;
}

export interface FieldDefinition {
    key: FieldKey;
    /** French label. */
    label: string;
    category: FieldCategory;
    type: ConditionValueType;
    options?: FieldOption[];
    /** Override the default operator set of `type`. */
    operators?: ConditionOperator[];
}

export interface FieldCatalog {
    fields: FieldDefinition[];
}

export interface ConditionOption<V extends string | number = string> {
    value: V;
    label: string;
}

/** Lookup sources for value pickers; injected so the component never calls the API itself. */
export interface ConditionOptionSources {
    lists?: (q: string) => Promise<ConditionOption[]>;
    campaigns?: (q: string) => Promise<ConditionOption[]>;
    users?: (q: string) => Promise<ConditionOption[]>;
    tags?: (q: string) => Promise<ConditionOption<number>[]>;
}

export interface ConditionBuilderProps {
    /** Current rule tree (controlled). `null` = empty builder (renders one empty group). */
    value: RuleTree | null;
    /** Called with a new tree on every change. Incomplete rows are kept with `value: null`. */
    onChange: (tree: RuleTree) => void;
    /** Built with `buildFieldCatalog(attributes, options)` from GET /contact-attributes + static fields. */
    fieldCatalog: FieldCatalog;
    /** Live count (debounced 600 ms by the component). Omit to hide the count button/badge. */
    onCount?: (tree: RuleTree, signal: AbortSignal) => Promise<number>;
    optionSources?: ConditionOptionSources;
    /** Default 100 (Brevo's limit). */
    maxConditions?: number;
    /** Restrict available categories (e.g. automations hide "pagejaunes"). Default: all. */
    categories?: FieldCategory[];
    /** Server validation errors keyed by dot path relative to the tree root (e.g. "children.0.value"). */
    errors?: Record<string, string>;
    disabled?: boolean;
    /** "full" (segment page, groups allowed) | "compact" (Contacts filter bar: one group, inline). */
    variant?: 'full' | 'compact';
    /** Rendered in the footer, e.g. "Enregistrer comme segment" / "Appliquer". */
    footer?: ReactNode;
}
