import axios from 'axios';
import type { Template } from '@/Lib/types/templates';

/**
 * docs/29-api-specification.md — /api/v1/templates.
 */
export async function fetchTemplates(): Promise<Template[]> {
    const response = await axios.get<{ data: Template[] }>('/api/v1/templates');

    return response.data.data;
}

export async function createTemplate(payload: { name: string; category?: string; html_content: string }): Promise<Template> {
    const response = await axios.post<{ data: Template }>('/api/v1/templates', payload);

    return response.data.data;
}

export async function archiveTemplate(id: string): Promise<Template> {
    const response = await axios.post<{ data: Template }>(`/api/v1/templates/${id}/archive`);

    return response.data.data;
}

export async function deleteTemplate(id: string): Promise<void> {
    await axios.delete(`/api/v1/templates/${id}`);
}
