import { emptyRuleTree, isRuleTreeComplete } from '@/Components/Contacts/ConditionBuilder';
import type {
    AutomationStatus,
    AutomationStep,
    AutomationStepConfigs,
    AutomationStepInput,
    AutomationStepStats,
    AutomationStepType,
    AutomationStepTypedConfig,
    AutomationTrigger,
    AutomationTriggerType,
    AutomationWaitUnit,
} from '@/Lib/types/automations';
import type { Uuid } from '@/Lib/types/common';
import type { FieldCatalog } from '@/Lib/types/segments';

/**
 * docs/45-brevo-api-contracts.md §45.7.5 — client-side model of the workflow
 * tree edited by `Automations/Edit`. A draft step carries a stable client
 * `key` (React key, drawer target, error owner) next to the server `id`
 * (kept so `PUT …/steps` preserves the stats of existing steps).
 */

/** §45.7.5 limits. */
export const MAX_STEPS = 50;
export const MAX_CONDITION_DEPTH = 3;
const MAX_WAIT_MINUTES: Record<AutomationWaitUnit, number> = { minutes: 525_600, hours: 8_760, days: 365 };

/** Status badge colours (label: fr.automations.statusLabels). */
export const AUTOMATION_STATUS_BADGE: Record<AutomationStatus, 'success' | 'warning' | 'informative'> = {
    draft: 'informative',
    active: 'success',
    paused: 'warning',
};

export const STEP_TYPES: AutomationStepType[] = [
    'send_email',
    'wait',
    'condition',
    'add_to_list',
    'remove_from_list',
    'update_attribute',
    'end',
];

export const TRIGGER_TYPES: AutomationTriggerType[] = [
    'list_added',
    'form_submitted',
    'campaign_opened',
    'campaign_clicked',
    'attribute_changed',
    'date_anniversary',
];

interface DraftStepFields {
    key: string;
    id?: Uuid;
    yes: DraftStep[];
    no: DraftStep[];
    stats?: AutomationStepStats;
}

export type DraftStep = DraftStepFields & AutomationStepTypedConfig;

/** Where a list of steps lives: the root list or a branch of a condition step. */
export type BranchRef = { parentKey: null } | { parentKey: string; branch: 'yes' | 'no' };

let keySeed = 0;
export function newKey(): string {
    keySeed += 1;

    return `s${Date.now().toString(36)}${keySeed}`;
}

export function defaultConfig<T extends AutomationStepType>(type: T): AutomationStepConfigs[T] {
    const configs: AutomationStepConfigs = {
        send_email: { template_id: '', subject: null, sender_id: null, preview_text: null },
        wait: { amount: 1, unit: 'days' },
        condition: { rules: emptyRuleTree() },
        add_to_list: { list_id: '' },
        remove_from_list: { list_id: '' },
        update_attribute: { attribute: '', value: null },
        end: {},
    };

    return configs[type];
}

export function createDraftStep(type: AutomationStepType): DraftStep {
    return { key: newKey(), type, config: defaultConfig(type), yes: [], no: [] } as DraftStep;
}

export function defaultTrigger(type: AutomationTriggerType): AutomationTrigger {
    switch (type) {
        case 'list_added':
            return { type, config: { list_ids: [] } };
        case 'form_submitted':
            return { type, config: { form_ids: [] } };
        case 'campaign_opened':
            return { type, config: { campaign_ids: null } };
        case 'campaign_clicked':
            return { type, config: { campaign_ids: null, link_url: null } };
        case 'attribute_changed':
            return { type, config: { attribute: '', to_value: null } };
        case 'date_anniversary':
            return { type, config: { attribute: '', offset_days: 0, time: '09:00' } };
    }
}

/** A trigger is configured when the editor would accept it (activation gate). */
export function isTriggerConfigured(trigger: AutomationTrigger | null, catalog?: FieldCatalog): boolean {
    if (!trigger) return false;
    const filter = trigger.config.filter;
    if (filter && filter.children.length > 0 && !isRuleTreeComplete(filter, catalog)) return false;
    switch (trigger.type) {
        case 'list_added':
            return trigger.config.list_ids.length > 0;
        case 'form_submitted':
            return trigger.config.form_ids.length > 0;
        case 'campaign_opened':
        case 'campaign_clicked':
            return trigger.config.campaign_ids === null || trigger.config.campaign_ids.length > 0;
        case 'attribute_changed':
            return trigger.config.attribute !== '';
        case 'date_anniversary':
            return trigger.config.attribute !== '' && /^\d{2}:\d{2}$/.test(trigger.config.time);
    }
}

// ---------------------------------------------------------------------------
// Conversions
// ---------------------------------------------------------------------------

export function fromServerSteps(steps: AutomationStep[]): DraftStep[] {
    return [...steps]
        .sort((a, b) => a.position - b.position)
        .map(
            (step) =>
                ({
                    key: newKey(),
                    id: step.id,
                    type: step.type,
                    config: step.config,
                    yes: step.type === 'condition' ? fromServerSteps(step.yes ?? []) : [],
                    no: step.type === 'condition' ? fromServerSteps(step.no ?? []) : [],
                    stats: step.stats,
                }) as DraftStep,
        );
}

export function toStepInputs(steps: DraftStep[]): AutomationStepInput[] {
    return steps.map(
        (step, position) =>
            ({
                ...(step.id ? { id: step.id } : {}),
                type: step.type,
                config: step.config,
                position,
                yes: step.type === 'condition' ? toStepInputs(step.yes) : [],
                no: step.type === 'condition' ? toStepInputs(step.no) : [],
            }) as AutomationStepInput,
    );
}

// ---------------------------------------------------------------------------
// Traversal and immutable updates
// ---------------------------------------------------------------------------

export function countSteps(steps: DraftStep[]): number {
    return steps.reduce((sum, step) => sum + 1 + countSteps(step.yes) + countSteps(step.no), 0);
}

export function findStep(steps: DraftStep[], key: string): DraftStep | null {
    for (const step of steps) {
        if (step.key === key) return step;
        const nested = findStep(step.yes, key) ?? findStep(step.no, key);
        if (nested) return nested;
    }

    return null;
}

/** Number of condition steps above a branch (0 for the root list). */
export function branchDepth(steps: DraftStep[], ref: BranchRef): number {
    if (ref.parentKey === null) return 0;
    const walk = (list: DraftStep[], depth: number): number | null => {
        for (const step of list) {
            if (step.key === ref.parentKey) return depth + 1;
            const found = walk(step.yes, depth + 1) ?? walk(step.no, depth + 1);
            if (found !== null) return found;
        }

        return null;
    };

    return walk(steps, 0) ?? 0;
}

function mapLists(steps: DraftStep[], ref: BranchRef, update: (list: DraftStep[]) => DraftStep[]): DraftStep[] {
    if (ref.parentKey === null) return update(steps);

    return steps.map((step) => {
        if (step.key === ref.parentKey) {
            return { ...step, [ref.branch]: update(step[ref.branch]) } as DraftStep;
        }
        if (step.yes.length === 0 && step.no.length === 0) return step;

        return { ...step, yes: mapLists(step.yes, ref, update), no: mapLists(step.no, ref, update) } as DraftStep;
    });
}

export function insertStep(steps: DraftStep[], ref: BranchRef, index: number, step: DraftStep): DraftStep[] {
    return mapLists(steps, ref, (list) => [...list.slice(0, index), step, ...list.slice(index)]);
}

export function removeStep(steps: DraftStep[], key: string): DraftStep[] {
    return steps
        .filter((step) => step.key !== key)
        .map((step) =>
            step.yes.length === 0 && step.no.length === 0
                ? step
                : ({ ...step, yes: removeStep(step.yes, key), no: removeStep(step.no, key) } as DraftStep),
        );
}

export function replaceStep(steps: DraftStep[], key: string, next: DraftStep): DraftStep[] {
    return steps.map((step) => {
        if (step.key === key) return next;
        if (step.yes.length === 0 && step.no.length === 0) return step;

        return { ...step, yes: replaceStep(step.yes, key, next), no: replaceStep(step.no, key, next) } as DraftStep;
    });
}

/** A list ends the contact's journey when its last step is `end` (or a condition whose branches both end). */
export function listTerminates(list: DraftStep[]): boolean {
    const last = list[list.length - 1];
    if (!last) return false;
    if (last.type === 'end') return true;
    if (last.type === 'condition') return listTerminates(last.yes) && listTerminates(last.no);

    return false;
}

/** Horizontal width in "columns": a condition needs two equal columns for its branches. */
export function widthUnits(list: DraftStep[]): number {
    return list.reduce(
        (max, step) => Math.max(max, step.type === 'condition' ? 2 * Math.max(widthUnits(step.yes), widthUnits(step.no)) : 1),
        1,
    );
}

/** Pre-order numbering (`Étape N`) and lookup by server id, for the runs list. */
export function numberSteps(steps: DraftStep[]): Map<string, { number: number; step: DraftStep }> {
    const out = new Map<string, { number: number; step: DraftStep }>();
    let counter = 0;
    const walk = (list: DraftStep[]) => {
        for (const step of list) {
            counter += 1;
            out.set(step.key, { number: counter, step });
            walk(step.yes);
            walk(step.no);
        }
    };
    walk(steps);

    return out;
}

// ---------------------------------------------------------------------------
// Validation
// ---------------------------------------------------------------------------

export type StepIssue = 'incomplete' | 'wait_range';

export function stepIssue(step: DraftStep, catalog?: FieldCatalog): StepIssue | null {
    switch (step.type) {
        case 'send_email':
            return step.config.template_id ? null : 'incomplete';
        case 'wait': {
            const { amount, unit } = step.config;

            return Number.isInteger(amount) && amount >= 1 && amount <= MAX_WAIT_MINUTES[unit] ? null : 'wait_range';
        }
        case 'condition':
            return step.config.rules.children.length > 0 && isRuleTreeComplete(step.config.rules, catalog) ? null : 'incomplete';
        case 'add_to_list':
        case 'remove_from_list':
            return step.config.list_id ? null : 'incomplete';
        case 'update_attribute': {
            const value = step.config.value;
            const empty = value === null || value === '' || (Array.isArray(value) && value.length === 0);

            return step.config.attribute && !empty ? null : 'incomplete';
        }
        case 'end':
            return null;
    }
}

export function collectIssues(steps: DraftStep[], catalog?: FieldCatalog): Map<string, StepIssue> {
    const out = new Map<string, StepIssue>();
    const walk = (list: DraftStep[]) => {
        for (const step of list) {
            const issue = stepIssue(step, catalog);
            if (issue) out.set(step.key, issue);
            walk(step.yes);
            walk(step.no);
        }
    };
    walk(steps);

    return out;
}

// ---------------------------------------------------------------------------
// Server validation errors (`errors.steps.0.yes.1.config.template_id`)
// ---------------------------------------------------------------------------

export interface MappedStepErrors {
    /** Per step key: messages keyed relative to the step (`config.template_id`, `type`, …; `""` = the step itself). */
    byStep: Record<string, Record<string, string>>;
    /** Messages addressed to the whole tree (`steps`) or to a path that no longer exists. */
    general: string[];
}

/**
 * Maps 422 keys under `steps.` onto the draft steps that were submitted
 * (paths follow the `PUT …/steps` body: `steps.N`, `steps.N.yes.M`, …).
 */
export function mapStepErrors(errors: Record<string, string>, submitted: DraftStep[]): MappedStepErrors {
    const paths: Array<{ path: string; key: string }> = [];
    const walk = (list: DraftStep[], prefix: string) => {
        list.forEach((step, index) => {
            const path = `${prefix}.${index}`;
            paths.push({ path, key: step.key });
            walk(step.yes, `${path}.yes`);
            walk(step.no, `${path}.no`);
        });
    };
    walk(submitted, 'steps');
    // Longest path first so a nested step wins over its condition parent.
    paths.sort((a, b) => b.path.length - a.path.length);

    const result: MappedStepErrors = { byStep: {}, general: [] };
    for (const [errorKey, message] of Object.entries(errors)) {
        if (errorKey !== 'steps' && !errorKey.startsWith('steps.')) continue;
        const owner = paths.find((p) => errorKey === p.path || errorKey.startsWith(`${p.path}.`));
        if (!owner) {
            result.general.push(message);
            continue;
        }
        const relative = errorKey === owner.path ? '' : errorKey.slice(owner.path.length + 1);
        result.byStep[owner.key] = { ...(result.byStep[owner.key] ?? {}), [relative]: message };
    }

    return result;
}

/** `config.rules.children.0.value` → `children.0.value` for ConditionBuilder.errors. */
export function conditionRuleErrors(errors: Record<string, string> | undefined): Record<string, string> {
    const out: Record<string, string> = {};
    for (const [key, message] of Object.entries(errors ?? {})) {
        if (key === 'config.rules') out[''] = message;
        else if (key.startsWith('config.rules.')) out[key.slice('config.rules.'.length)] = message;
    }

    return out;
}

/** `trigger.config.filter.children.0.value` → `children.0.value`. */
export function triggerFilterErrors(errors: Record<string, string>): Record<string, string> {
    const out: Record<string, string> = {};
    for (const [key, message] of Object.entries(errors)) {
        if (key === 'trigger.config.filter') out[''] = message;
        else if (key.startsWith('trigger.config.filter.')) out[key.slice('trigger.config.filter.'.length)] = message;
    }

    return out;
}
