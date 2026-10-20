import { useState } from 'react';
import { usePage } from '@inertiajs/react';
import { Button, Spinner, Tooltip } from '@fluentui/react-components';
import { ArrowDownloadRegular } from '@fluentui/react-icons';
import { useText } from '@/Hooks/useText';
import { hasPermission } from '@/Lib/permissions';
import { downloadReportExport } from '@/Lib/api/reports';
import { apiErrorMessage } from '@/Components/Campaigns/campaignUi';
import type { ReportTab } from '@/Lib/types/reports';

export interface ExportButtonProps {
    campaignId: string;
    campaignName: string;
    tab: ReportTab;
    onError: (message: string) => void;
}

/** **Exporter** — CSV of the current tab (`GET report/export?tab=`, `reporting.export`). */
export function ExportButton({ campaignId, campaignName, tab, onError }: ExportButtonProps) {
    const t = useText();
    const { props } = usePage<{ auth: { permissions: string[] } }>();
    const allowed = hasPermission(props.auth?.permissions ?? [], 'reporting.export');
    const [busy, setBusy] = useState(false);

    async function run() {
        setBusy(true);
        const slug = campaignName
            .normalize('NFD')
            .replace(/[̀-ͯ]/g, '')
            .replace(/[^a-zA-Z0-9]+/g, '-')
            .replace(/^-|-$/g, '')
            .toLowerCase();
        try {
            await downloadReportExport(campaignId, tab, `rapport-${slug || campaignId}-${tab}.csv`);
        } catch (error) {
            onError(apiErrorMessage(error));
        } finally {
            setBusy(false);
        }
    }

    return (
        <Tooltip content={allowed ? t.campaignReport.exportTab : t.common.forbidden} relationship="description">
            <Button
                icon={busy ? <Spinner size="extra-tiny" /> : <ArrowDownloadRegular />}
                disabledFocusable={!allowed}
                disabled={busy}
                onClick={() => void run()}
            >
                {t.campaignReport.export}
            </Button>
        </Tooltip>
    );
}
