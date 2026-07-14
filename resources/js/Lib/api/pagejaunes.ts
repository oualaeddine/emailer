import axios from 'axios';
import type { PageJaunesCompany } from '@/Lib/types/pagejaunes';

/**
 * docs/29-api-specification.md §29.4 — GET /api/v1/pagejaunes/search.
 */
export async function searchPageJaunesCompanies(query: string, page = 1): Promise<PageJaunesCompany[]> {
    const response = await axios.get<{ data: PageJaunesCompany[] }>('/api/v1/pagejaunes/search', {
        params: { q: query, page },
    });

    return response.data.data;
}
