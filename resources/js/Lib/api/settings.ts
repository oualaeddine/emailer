import axios from 'axios';
import type { Setting } from '@/Lib/types/settings';

/**
 * docs/29-api-specification.md §29.11 — GET/PATCH /api/v1/settings.
 */
export async function fetchSettings(): Promise<Setting[]> {
    const response = await axios.get<{ data: Setting[] }>('/api/v1/settings');

    return response.data.data;
}

export async function updateSettings(settings: Record<string, string>): Promise<Setting[]> {
    const response = await axios.patch<{ data: Setting[] }>('/api/v1/settings', { settings });

    return response.data.data;
}
