import axios from 'axios';
import type { DataItem, DataList } from '@/Lib/types/common';
import type {
    AttributeUsageRef,
    ContactAttribute,
    ContactAttributeInput,
    ContactAttributeUpdateInput,
} from '@/Lib/types/attributes';

/**
 * docs/45-brevo-api-contracts.md §45.5.1 — /api/v1/contact-attributes.
 */
export async function fetchAttributes(includeUsage = true): Promise<ContactAttribute[]> {
    const response = await axios.get<DataList<ContactAttribute>>('/api/v1/contact-attributes', {
        params: includeUsage ? { include_usage: 1 } : {},
    });

    return response.data.data;
}

export async function createAttribute(payload: ContactAttributeInput): Promise<ContactAttribute> {
    const response = await axios.post<DataItem<ContactAttribute>>('/api/v1/contact-attributes', payload);

    return response.data.data;
}

export async function updateAttribute(id: string, payload: ContactAttributeUpdateInput): Promise<ContactAttribute> {
    const response = await axios.patch<DataItem<ContactAttribute>>(`/api/v1/contact-attributes/${id}`, payload);

    return response.data.data;
}

export async function deleteAttribute(id: string): Promise<void> {
    await axios.delete(`/api/v1/contact-attributes/${id}`, { data: { confirm: true } });
}

interface ApiErrorShape {
    response?: {
        status?: number;
        data?: { message?: string; errors?: Record<string, unknown> };
    };
}

/** Extracts the French message of a doc 29 §29.1 error envelope (first field error on 422). */
export function apiErrorMessage(error: unknown, fallback: string): string {
    const data = (error as ApiErrorShape)?.response?.data;
    const errors = data?.errors;
    if (errors && !('used_in' in errors)) {
        for (const value of Object.values(errors)) {
            if (Array.isArray(value) && typeof value[0] === 'string') {
                return value[0];
            }
        }
    }

    return data?.message ?? fallback;
}

/** `errors.used_in` of a 409 on DELETE / PATCH, or [] when absent. */
export function usedInFromError(error: unknown): AttributeUsageRef[] {
    const used = (error as ApiErrorShape)?.response?.data?.errors?.used_in;

    return Array.isArray(used) ? (used as AttributeUsageRef[]) : [];
}

export function errorStatus(error: unknown): number | undefined {
    return (error as ApiErrorShape)?.response?.status;
}
