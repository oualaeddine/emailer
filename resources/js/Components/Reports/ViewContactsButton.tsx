import { useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import { Button, Menu, MenuItem, MenuList, MenuPopover, MenuTrigger, Spinner, Tooltip } from '@fluentui/react-components';
import { PeopleSearchRegular } from '@fluentui/react-icons';
import { useText } from '@/Hooks/useText';
import { hasPermission } from '@/Lib/permissions';
import { createReportSegment } from '@/Lib/api/reports';
import { apiErrorMessage } from '@/Components/Campaigns/campaignUi';
import type { ReportSegmentMetric } from '@/Lib/types/reports';

export interface ViewContactsButtonProps {
    campaignId: string;
    campaignName: string;
    /** One metric → a button; several → a menu (e.g. opened / not opened). */
    metrics: ReportSegmentMetric[];
    /** Per-link "clicked" segment (Clics tab). */
    linkId?: string | null;
    linkLabel?: string;
    size?: 'small' | 'medium';
    appearance?: 'secondary' | 'subtle';
    onError: (message: string) => void;
}

/**
 * **Voir les contacts** — `POST campaigns/{id}/report/segment` builds a segment
 * "A ouvert « … »" from the metric, then opens it in Contacts › Segments.
 */
export function ViewContactsButton({
    campaignId,
    campaignName,
    metrics,
    linkId = null,
    linkLabel,
    size = 'small',
    appearance = 'secondary',
    onError,
}: ViewContactsButtonProps) {
    const t = useText();
    const { props } = usePage<{ auth: { permissions: string[] } }>();
    const canCreate = hasPermission(props.auth?.permissions ?? [], 'segments.manage');
    const [busy, setBusy] = useState(false);

    async function create(metric: ReportSegmentMetric) {
        setBusy(true);
        const subject = linkLabel ? `${campaignName} — ${linkLabel}` : campaignName;
        try {
            const segment = await createReportSegment(campaignId, {
                metric,
                link_id: linkId,
                name: `${t.campaignReport.contactMetrics[metric]} « ${subject} »`.slice(0, 255),
                folder_id: null,
            });
            router.visit(`/contacts/segments/${segment.id}`);
        } catch (error) {
            onError(apiErrorMessage(error));
            setBusy(false);
        }
    }

    const icon = busy ? <Spinner size="extra-tiny" /> : <PeopleSearchRegular />;

    if (!canCreate) {
        return (
            <Tooltip content={t.common.forbidden} relationship="description">
                <Button size={size} appearance={appearance} icon={<PeopleSearchRegular />} disabledFocusable>
                    {t.campaignReport.viewContacts}
                </Button>
            </Tooltip>
        );
    }

    if (metrics.length === 1) {
        return (
            <Button size={size} appearance={appearance} icon={icon} disabled={busy} onClick={() => void create(metrics[0])}>
                {t.campaignReport.viewContacts}
            </Button>
        );
    }

    return (
        <Menu>
            <MenuTrigger disableButtonEnhancement>
                <Button size={size} appearance={appearance} icon={icon} disabled={busy}>
                    {t.campaignReport.viewContacts}
                </Button>
            </MenuTrigger>
            <MenuPopover>
                <MenuList>
                    {metrics.map((metric) => (
                        <MenuItem key={metric} onClick={() => void create(metric)}>
                            {t.campaignReport.contactMetrics[metric]}
                        </MenuItem>
                    ))}
                </MenuList>
            </MenuPopover>
        </Menu>
    );
}
