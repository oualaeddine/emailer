import axios from 'axios';
import type { CursorPaginatedResponse } from '@/Lib/types/audit';
import type {
    Automation,
    AutomationActivity,
    AutomationDetail,
    AutomationInput,
    AutomationListQuery,
    AutomationRun,
    AutomationRunsQuery,
    AutomationStepInput,
    AutomationStepsInput,
} from '@/Lib/types/automations';
import type { CampaignListResponse } from '@/Lib/types/campaigns';
import type { DataItem, IsoDate, Uuid } from '@/Lib/types/common';
import type { SubscriptionForm } from '@/Lib/types/forms';
import type { PaginatedResponse } from '@/Lib/types/identity';
import type { ContactList } from '@/Lib/types/lists';
import type { TemplateV2 } from '@/Lib/types/templates';

/**
 * docs/45-brevo-api-contracts.md §45.7.5 — Automations (/api/v1/automations)
 * plus the read-only lookups the workflow editor needs: §45.5.6 GET
 * /recipient-lists (list trigger, add/remove list steps), §45.7.4 GET /forms
 * (form trigger), §45.4.4 GET /templates?scope=transactional (send e-mail
 * steps — automation e-mails go through the transactional path) and §45.4.2
 * GET /campaigns?status_tab=sent (campaign opened / clicked triggers).
 * GET /contact-attributes comes from the shared ConditionBuilder helpers.
 */

const BASE = '/api/v1';
/** §45.1: per_page ≤ 100. */
const LOOKUP_PAGE_SIZE = 100;

// ---------------------------------------------------------------------------
// Automations
// ---------------------------------------------------------------------------

export async function fetchAutomations(query: AutomationListQuery = {}): Promise<PaginatedResponse<Automation>> {
    const response = await axios.get<PaginatedResponse<Automation>>(`${BASE}/automations`, { params: query });

    return response.data;
}

export async function fetchAutomation(id: Uuid): Promise<AutomationDetail> {
    const response = await axios.get<DataItem<AutomationDetail>>(`${BASE}/automations/${id}`);

    return response.data.data;
}

export async function createAutomation(input: AutomationInput): Promise<AutomationDetail> {
    const response = await axios.post<DataItem<AutomationDetail>>(`${BASE}/automations`, input);

    return response.data.data;
}

/** Partial, never steps. `409` when the trigger changes while active. */
export async function updateAutomation(id: Uuid, input: Partial<AutomationInput>): Promise<AutomationDetail> {
    const response = await axios.patch<DataItem<AutomationDetail>>(`${BASE}/automations/${id}`, input);

    return response.data.data;
}

/** Replaces the whole step tree. `409` while active with runs in progress; `422 errors.steps.N.*`. */
export async function saveAutomationSteps(id: Uuid, steps: AutomationStepInput[]): Promise<AutomationDetail> {
    const body: AutomationStepsInput = { steps };
    const response = await axios.put<DataItem<AutomationDetail>>(`${BASE}/automations/${id}/steps`, body);

    return response.data.data;
}

/** `409` while active. */
export async function deleteAutomation(id: Uuid): Promise<void> {
    await axios.delete(`${BASE}/automations/${id}`);
}

/** `422 errors.steps` when the workflow is invalid (no steps, missing template, …). */
export async function activateAutomation(id: Uuid): Promise<Automation> {
    const response = await axios.post<DataItem<Automation>>(`${BASE}/automations/${id}/activate`);

    return response.data.data;
}

/** Runs freeze; resume via activate. */
export async function pauseAutomation(id: Uuid): Promise<Automation> {
    const response = await axios.post<DataItem<Automation>>(`${BASE}/automations/${id}/pause`);

    return response.data.data;
}

/** `201`, status draft. */
export async function duplicateAutomation(id: Uuid): Promise<Automation> {
    const response = await axios.post<DataItem<Automation>>(`${BASE}/automations/${id}/duplicate`);

    return response.data.data;
}

export async function fetchAutomationRuns(id: Uuid, query: AutomationRunsQuery = {}): Promise<CursorPaginatedResponse<AutomationRun>> {
    const response = await axios.get<CursorPaginatedResponse<AutomationRun>>(`${BASE}/automations/${id}/runs`, { params: query });

    return response.data;
}

export async function exitAutomationRun(id: Uuid, runId: Uuid): Promise<AutomationRun> {
    const response = await axios.post<DataItem<AutomationRun>>(`${BASE}/automations/${id}/runs/${runId}/exit`);

    return response.data.data;
}

export interface AutomationActivityQuery {
    date_from?: IsoDate;
    date_to?: IsoDate;
}

export async function fetchAutomationActivity(id: Uuid, query: AutomationActivityQuery = {}): Promise<AutomationActivity> {
    const response = await axios.get<DataItem<AutomationActivity>>(`${BASE}/automations/${id}/activity`, { params: query });

    return response.data.data;
}

// ---------------------------------------------------------------------------
// Editor lookups (trigger and step pickers)
// ---------------------------------------------------------------------------

export interface LookupOption {
    value: Uuid;
    label: string;
    /** Templates only: the template's own subject (placeholder of the override). */
    hint?: string | null;
}

/** §45.5.6 GET /recipient-lists. */
export async function searchAutomationLists(q: string): Promise<LookupOption[]> {
    const response = await axios.get<PaginatedResponse<ContactList>>(`${BASE}/recipient-lists`, {
        params: { ...(q ? { q } : {}), sort: 'name', per_page: LOOKUP_PAGE_SIZE },
    });

    return response.data.data.map((list) => ({ value: list.id, label: list.name }));
}

/** §45.7.4 GET /forms. */
export async function searchAutomationForms(q: string): Promise<LookupOption[]> {
    const response = await axios.get<PaginatedResponse<SubscriptionForm>>(`${BASE}/forms`, {
        params: { ...(q ? { q } : {}), per_page: LOOKUP_PAGE_SIZE },
    });

    return response.data.data.map((form) => ({ value: form.id, label: form.name }));
}

/** §45.4.4 GET /templates?scope=transactional (non-archived, own templates). */
export async function searchAutomationTemplates(q: string): Promise<LookupOption[]> {
    const response = await axios.get<PaginatedResponse<TemplateV2>>(`${BASE}/templates`, {
        params: {
            scope: 'transactional',
            is_gallery: 0,
            is_archived: 0,
            ...(q ? { q } : {}),
            sort: 'name',
            per_page: LOOKUP_PAGE_SIZE,
        },
    });

    return response.data.data.map((template) => ({ value: template.id, label: template.name, hint: template.subject }));
}

/** §45.4.2 GET /campaigns?status_tab=sent. */
export async function searchAutomationCampaigns(q: string): Promise<LookupOption[]> {
    const response = await axios.get<CampaignListResponse>(`${BASE}/campaigns`, {
        params: { status_tab: 'sent', ...(q ? { q } : {}), sort: '-sent_at', per_page: LOOKUP_PAGE_SIZE },
    });

    return response.data.data.map((campaign) => ({ value: campaign.id, label: campaign.name }));
}

/** Grouped client. */
export const automations = {
    list: fetchAutomations,
    get: fetchAutomation,
    create: createAutomation,
    update: updateAutomation,
    saveSteps: saveAutomationSteps,
    remove: deleteAutomation,
    activate: activateAutomation,
    pause: pauseAutomation,
    duplicate: duplicateAutomation,
    runs: fetchAutomationRuns,
    exitRun: exitAutomationRun,
    activity: fetchAutomationActivity,
    lists: searchAutomationLists,
    forms: searchAutomationForms,
    templates: searchAutomationTemplates,
    campaigns: searchAutomationCampaigns,
};
