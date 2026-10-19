/**
 * docs/45-brevo-api-contracts.md §45.7.5 — WP-91 automations backend,
 * WP-91U editor (`Automations/Index`, `Automations/Edit`).
 */
import type { ContactAttributeValue } from '@/Lib/types/attributes';
import type { IsoDateTime, UserRef, Uuid } from '@/Lib/types/common';
import type { BrevoStats, RateSet } from '@/Lib/types/reports';
import type { RuleTree } from '@/Lib/types/segments';

/** Labels: fr.automations.statusLabels. */
export type AutomationStatus = 'draft' | 'active' | 'paused';

/** Labels: fr.automations.triggers. */
export type AutomationTriggerType =
    | 'list_added'
    | 'form_submitted'
    | 'campaign_opened'
    | 'campaign_clicked'
    | 'attribute_changed'
    | 'date_anniversary';

/** Labels: fr.automations.steps. */
export type AutomationStepType =
    | 'send_email'
    | 'wait'
    | 'condition'
    | 'add_to_list'
    | 'remove_from_list'
    | 'update_attribute'
    | 'end';

/** Labels: fr.automations.runStatus. */
export type AutomationRunStatus = 'active' | 'waiting' | 'completed' | 'exited' | 'failed';

/** Labels: fr.automations.waitUnits. */
export type AutomationWaitUnit = 'minutes' | 'hours' | 'days';

interface TriggerFilter {
    /** Only contacts matching enter. */
    filter?: RuleTree | null;
}

export type AutomationTrigger =
    | { type: 'list_added'; config: TriggerFilter & { list_ids: Uuid[] } }
    | { type: 'form_submitted'; config: TriggerFilter & { form_ids: Uuid[] } }
    | { type: 'campaign_opened'; config: TriggerFilter & { campaign_ids: Uuid[] | null } }
    | { type: 'campaign_clicked'; config: TriggerFilter & { campaign_ids: Uuid[] | null; link_url: string | null } }
    | { type: 'attribute_changed'; config: TriggerFilter & { attribute: string; to_value: ContactAttributeValue } }
    | {
          type: 'date_anniversary';
          config: TriggerFilter & { attribute: string; offset_days: number; time: string };
      };

export interface SendEmailStepConfig {
    template_id: Uuid;
    subject: string | null;
    sender_id: Uuid | null;
    preview_text: string | null;
}

export interface WaitStepConfig {
    amount: number;
    unit: AutomationWaitUnit;
}

export interface ConditionStepConfig {
    rules: RuleTree;
}

export interface ListStepConfig {
    list_id: Uuid;
}

export interface UpdateAttributeStepConfig {
    attribute: string;
    value: ContactAttributeValue;
}

export interface AutomationStepConfigs {
    send_email: SendEmailStepConfig;
    wait: WaitStepConfig;
    condition: ConditionStepConfig;
    add_to_list: ListStepConfig;
    remove_from_list: ListStepConfig;
    update_attribute: UpdateAttributeStepConfig;
    end: Record<string, never>;
}

export interface AutomationStepStats {
    entered: number;
    completed: number;
}

/** Discriminated `type` + `config` pair. */
export type AutomationStepTypedConfig = {
    [T in AutomationStepType]: { type: T; config: AutomationStepConfigs[T] };
}[AutomationStepType];

interface AutomationStepFields {
    id: Uuid;
    position: number;
    /** Condition steps only; [] otherwise. */
    yes: AutomationStep[];
    no: AutomationStep[];
    stats: AutomationStepStats;
}

/** Response step (tree: condition steps carry yes/no branches). */
export type AutomationStep = AutomationStepFields & AutomationStepTypedConfig;

interface AutomationStepInputFields {
    /** Optional, to keep stats of an existing step. */
    id?: Uuid;
    position: number;
    yes: AutomationStepInput[];
    no: AutomationStepInput[];
}

/** PUT /automations/{id}/steps item. */
export type AutomationStepInput = AutomationStepInputFields & AutomationStepTypedConfig;

export interface AutomationStats {
    started: number;
    in_progress: number;
    completed: number;
    exited: number;
}

export interface Automation {
    id: Uuid;
    name: string;
    description: string | null;
    status: AutomationStatus;
    trigger: AutomationTrigger;
    allow_reentry: boolean;
    reentry_after_days: number | null;
    stats: AutomationStats;
    created_by: UserRef | null;
    activated_at: IsoDateTime | null;
    created_at: IsoDateTime;
    updated_at: IsoDateTime;
}

export interface AutomationDetail extends Automation {
    steps: AutomationStep[];
}

export interface AutomationInput {
    name: string;
    description?: string | null;
    trigger: AutomationTrigger;
    allow_reentry?: boolean;
    reentry_after_days?: number | null;
}

export interface AutomationStepsInput {
    steps: AutomationStepInput[];
}

export interface AutomationListQuery {
    q?: string;
    status?: AutomationStatus;
    page?: number;
    per_page?: number;
}

export interface AutomationRun {
    id: Uuid;
    recipient: { id: Uuid; email: string };
    status: AutomationRunStatus;
    current_step_id: Uuid | null;
    next_run_at: IsoDateTime | null;
    started_at: IsoDateTime;
    finished_at: IsoDateTime | null;
    error: string | null;
}

export interface AutomationRunsQuery {
    status?: AutomationRunStatus;
    cursor?: string;
    per_page?: number;
}

export interface AutomationEmailActivity {
    step_id: Uuid;
    template_name: string;
    globalStats: BrevoStats;
    rates: RateSet;
}

/** GET /automations/{id}/activity ("Activité" tab). */
export interface AutomationActivity {
    started: number;
    completed: number;
    in_progress: number;
    exited: number;
    emails: AutomationEmailActivity[];
}
