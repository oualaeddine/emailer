import { useCallback, useEffect, useState } from 'react';
import { Head } from '@inertiajs/react';
import {
    Badge,
    Button,
    Drawer,
    DrawerBody,
    DrawerHeader,
    DrawerHeaderTitle,
    Menu,
    MenuItem,
    MenuList,
    MenuPopover,
    MenuTrigger,
    Title1,
    Toolbar,
    DataGrid,
    DataGridBody,
    DataGridCell,
    DataGridHeader,
    DataGridHeaderCell,
    DataGridRow,
    createTableColumn,
    makeStyles,
    tokens,
    type TableColumnDefinition,
} from '@fluentui/react-components';
import {
    AddRegular,
    CopyRegular,
    DismissCircleRegular,
    MoreHorizontalRegular,
    PauseRegular,
    PlayRegular,
} from '@fluentui/react-icons';
import { AppShell } from '@/Components/Shell/AppShell';
import { useText } from '@/Hooks/useText';
import { cancelCampaign, cloneCampaign, fetchCampaigns, pauseCampaign, resumeCampaign } from '@/Lib/api/campaigns';
import { fetchTemplates } from '@/Lib/api/templates';
import type { Campaign, CampaignStatus } from '@/Lib/types/campaigns';
import { CampaignDetail } from '@/Pages/Campaigns/CampaignDetail';
import { CampaignWizardDialog } from '@/Pages/Campaigns/CampaignWizardDialog';

const useStyles = makeStyles({
    header: {
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        marginBottom: tokens.spacingVerticalL,
    },
    card: {
        backgroundColor: tokens.colorNeutralBackground1,
        borderRadius: tokens.borderRadiusMedium,
        padding: tokens.spacingVerticalM,
    },
    nameButton: {
        padding: 0,
        minWidth: 0,
    },
});

const STATUS_COLOR: Record<CampaignStatus, 'subtle' | 'informative' | 'warning' | 'success' | 'danger'> = {
    draft: 'subtle',
    scheduled: 'informative',
    running: 'warning',
    completed: 'success',
    paused: 'warning',
    cancelled: 'danger',
};

/**
 * docs/15-campaign-management.md §15.6 — list view w/ status-gated row
 * actions (pause only while `running`, resume only while `paused`, cancel
 * while `draft`/`scheduled`/`paused`, clone always available per §15.7).
 * Status values match `App\Modules\Campaigns\Enums\CampaignStatus`
 * (`running`/`completed`, not `sending`/`sent`) — see `Lib/types/campaigns.ts`.
 */
export default function CampaignsIndex() {
    const styles = useStyles();
    const t = useText();

    const [campaigns, setCampaigns] = useState<Campaign[]>([]);
    const [templateNames, setTemplateNames] = useState<Record<string, string>>({});
    const [wizardOpen, setWizardOpen] = useState(false);
    const [selectedId, setSelectedId] = useState<string | null>(null);

    const load = useCallback(async () => {
        const response = await fetchCampaigns();
        setCampaigns(response.data);
    }, []);

    useEffect(() => {
        void load();
        // `CampaignResource` only exposes `template_id` (no embedded name) —
        // resolved here the same way `CampaignWizardDialog` resolves it.
        void fetchTemplates().then((templates) => {
            setTemplateNames(Object.fromEntries(templates.map((template) => [template.id, template.name])));
        });
    }, [load]);

    function statusLabel(status: CampaignStatus): string {
        const labels: Record<CampaignStatus, string> = {
            draft: t.campaigns.statusDraft,
            scheduled: t.campaigns.statusScheduled,
            running: t.campaigns.statusSending,
            completed: t.campaigns.statusSent,
            paused: t.campaigns.statusPaused,
            cancelled: t.campaigns.statusCancelled,
        };

        return labels[status];
    }

    function formatDate(value: string | null): string {
        return value ? new Date(value).toLocaleString('fr-FR') : '—';
    }

    async function handlePause(id: string) {
        await pauseCampaign(id);
        await load();
    }

    async function handleResume(id: string) {
        await resumeCampaign(id);
        await load();
    }

    async function handleCancel(id: string) {
        if (window.confirm(t.campaigns.confirmCancel)) {
            await cancelCampaign(id);
            await load();
        }
    }

    async function handleClone(id: string) {
        await cloneCampaign(id);
        await load();
    }

    const columns: TableColumnDefinition<Campaign>[] = [
        createTableColumn<Campaign>({
            columnId: 'name',
            renderHeaderCell: () => t.campaigns.name,
            renderCell: (c) => (
                <Button
                    appearance="transparent"
                    className={styles.nameButton}
                    onClick={() => setSelectedId(c.id)}
                >
                    {c.name}
                </Button>
            ),
        }),
        createTableColumn<Campaign>({
            columnId: 'status',
            renderHeaderCell: () => t.campaigns.status,
            renderCell: (c) => (
                <Badge appearance="tint" color={STATUS_COLOR[c.status]}>
                    {statusLabel(c.status)}
                </Badge>
            ),
        }),
        createTableColumn<Campaign>({
            columnId: 'template',
            renderHeaderCell: () => t.campaigns.template,
            renderCell: (c) => (c.template_id ? (templateNames[c.template_id] ?? c.template_id) : '—'),
        }),
        createTableColumn<Campaign>({
            columnId: 'scheduled_at',
            renderHeaderCell: () => t.campaigns.scheduledAt,
            renderCell: (c) => formatDate(c.scheduled_at),
        }),
        createTableColumn<Campaign>({
            columnId: 'sent_at',
            renderHeaderCell: () => t.campaigns.sentAt,
            renderCell: (c) => formatDate(c.sent_at),
        }),
        createTableColumn<Campaign>({
            columnId: 'actions',
            renderHeaderCell: () => t.common.actions,
            renderCell: (c) => (
                <Menu>
                    <MenuTrigger disableButtonEnhancement>
                        <Button appearance="transparent" icon={<MoreHorizontalRegular />} />
                    </MenuTrigger>
                    <MenuPopover>
                        <MenuList>
                            {c.status === 'running' && (
                                <MenuItem icon={<PauseRegular />} onClick={() => void handlePause(c.id)}>
                                    {t.campaigns.pause}
                                </MenuItem>
                            )}
                            {c.status === 'paused' && (
                                <MenuItem icon={<PlayRegular />} onClick={() => void handleResume(c.id)}>
                                    {t.campaigns.resume}
                                </MenuItem>
                            )}
                            {(c.status === 'draft' || c.status === 'scheduled' || c.status === 'paused') && (
                                <MenuItem icon={<DismissCircleRegular />} onClick={() => void handleCancel(c.id)}>
                                    {t.campaigns.cancel}
                                </MenuItem>
                            )}
                            <MenuItem icon={<CopyRegular />} onClick={() => void handleClone(c.id)}>
                                {t.campaigns.clone}
                            </MenuItem>
                        </MenuList>
                    </MenuPopover>
                </Menu>
            ),
        }),
    ];

    return (
        <AppShell>
            <Head title={t.campaigns.title} />
            <div className={styles.header}>
                <Title1>{t.campaigns.title}</Title1>
                <Toolbar>
                    <Button appearance="primary" icon={<AddRegular />} onClick={() => setWizardOpen(true)}>
                        {t.campaigns.newCampaign}
                    </Button>
                </Toolbar>
            </div>
            <div className={styles.card}>
                <DataGrid items={campaigns} columns={columns} getRowId={(c) => c.id} resizableColumns>
                    <DataGridHeader>
                        <DataGridRow>
                            {({ renderHeaderCell }) => <DataGridHeaderCell>{renderHeaderCell()}</DataGridHeaderCell>}
                        </DataGridRow>
                    </DataGridHeader>
                    <DataGridBody<Campaign>>
                        {({ item, rowId }) => (
                            <DataGridRow<Campaign> key={rowId}>
                                {({ renderCell }) => <DataGridCell>{renderCell(item)}</DataGridCell>}
                            </DataGridRow>
                        )}
                    </DataGridBody>
                </DataGrid>
            </div>

            <CampaignWizardDialog open={wizardOpen} onOpenChange={setWizardOpen} onCreated={load} />

            <Drawer
                open={selectedId !== null}
                onOpenChange={(_, data) => !data.open && setSelectedId(null)}
                position="end"
                style={{ width: '640px' }}
            >
                <DrawerHeader>
                    <DrawerHeaderTitle>{t.campaigns.title}</DrawerHeaderTitle>
                </DrawerHeader>
                <DrawerBody>
                    {selectedId && <CampaignDetail campaignId={selectedId} onChange={load} />}
                </DrawerBody>
            </Drawer>
        </AppShell>
    );
}
