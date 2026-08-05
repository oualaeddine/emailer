import axios from 'axios';
import type { DashboardWidgets } from '@/Lib/types/dashboard';

/**
 * docs/09-dashboard.md, docs/42-parallel-execution-plan.md §42.8 (WP-33) —
 * GET /api/v1/dashboard/widgets. Plain JSON object, not a `{data: ...}`
 * envelope (same convention as `CampaignController::analytics()`).
 */
export async function fetchDashboardWidgets(days = 7): Promise<DashboardWidgets> {
    const response = await axios.get<DashboardWidgets>('/api/v1/dashboard/widgets', {
        params: { days },
    });

    return response.data;
}
