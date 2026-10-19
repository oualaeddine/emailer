import axios from 'axios';

/**
 * Laravel 422 envelope → `{ "dot.path": "message" }` (first message per key).
 * Returns `null` when the error is not a validation error.
 */
export function validationErrors(error: unknown): Record<string, string> | null {
    if (!axios.isAxiosError(error) || error.response?.status !== 422) return null;
    const raw = (error.response.data as { errors?: Record<string, string | string[]> } | undefined)?.errors ?? {};
    const out: Record<string, string> = {};
    for (const [key, value] of Object.entries(raw)) {
        const message = Array.isArray(value) ? value[0] : value;
        if (message) out[key] = message;
    }

    return out;
}

/**
 * Errors addressed to the rule tree (`rules`, `rules.children.0.value`…)
 * re-keyed relative to the tree root, as `ConditionBuilder.errors` expects.
 */
export function ruleTreeErrors(errors: Record<string, string> | null | undefined): Record<string, string> {
    const out: Record<string, string> = {};
    for (const [key, message] of Object.entries(errors ?? {})) {
        if (key === 'rules') out[''] = message;
        else if (key.startsWith('rules.')) out[key.slice('rules.'.length)] = message;
    }

    return out;
}

/** First server message of an error response, if any (409 conflicts, …). */
export function errorMessage(error: unknown): string | null {
    if (!axios.isAxiosError(error)) return null;
    const message = (error.response?.data as { message?: unknown } | undefined)?.message;

    return typeof message === 'string' && message !== '' ? message : null;
}

export function errorStatus(error: unknown): number | null {
    return axios.isAxiosError(error) ? (error.response?.status ?? null) : null;
}
