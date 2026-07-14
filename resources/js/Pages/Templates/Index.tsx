import { useCallback, useEffect, useState } from 'react';
import { Head } from '@inertiajs/react';
import {
    Badge,
    Button,
    Card,
    CardHeader,
    CardPreview,
    Menu,
    MenuItem,
    MenuList,
    MenuPopover,
    MenuTrigger,
    Text,
    Title1,
    Toolbar,
    makeStyles,
    tokens,
} from '@fluentui/react-components';
import { AddRegular, MoreHorizontalRegular, ArchiveRegular, DeleteRegular } from '@fluentui/react-icons';
import { AppShell } from '@/Components/Shell/AppShell';
import { useText } from '@/Hooks/useText';
import { archiveTemplate, createTemplate, deleteTemplate, fetchTemplates } from '@/Lib/api/templates';
import type { Template } from '@/Lib/types/templates';
import { TemplateFormDialog } from '@/Pages/Templates/TemplateFormDialog';

const useStyles = makeStyles({
    header: {
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        marginBottom: tokens.spacingVerticalL,
    },
    grid: {
        display: 'grid',
        gridTemplateColumns: 'repeat(auto-fill, minmax(240px, 1fr))',
        gap: tokens.spacingHorizontalM,
    },
    preview: {
        height: '140px',
        overflow: 'hidden',
        backgroundColor: tokens.colorNeutralBackground3,
    },
    cardTop: {
        display: 'flex',
        justifyContent: 'space-between',
        alignItems: 'flex-start',
    },
});

/**
 * docs/12-template-system.md §12.2 — Template Library.
 */
export default function TemplatesIndex() {
    const styles = useStyles();
    const t = useText();

    const [templates, setTemplates] = useState<Template[]>([]);
    const [dialogOpen, setDialogOpen] = useState(false);

    const load = useCallback(async () => {
        const list = await fetchTemplates();
        setTemplates(list);
    }, []);

    useEffect(() => {
        void load();
    }, [load]);

    async function handleCreate(values: { name: string; category?: string; html_content: string }) {
        await createTemplate(values);
        setDialogOpen(false);
        await load();
    }

    async function handleArchive(id: string) {
        await archiveTemplate(id);
        await load();
    }

    async function handleDelete(id: string) {
        try {
            await deleteTemplate(id);
            await load();
        } catch (error) {
            if (typeof error === 'object' && error !== null && 'response' in error) {
                window.alert(t.templates.cannotDelete);
            } else {
                throw error;
            }
        }
    }

    return (
        <AppShell>
            <Head title={t.templates.title} />
            <div className={styles.header}>
                <Title1>{t.templates.title}</Title1>
                <Toolbar>
                    <Button appearance="primary" icon={<AddRegular />} onClick={() => setDialogOpen(true)}>
                        {t.templates.newTemplate}
                    </Button>
                </Toolbar>
            </div>
            <div className={styles.grid}>
                {templates.map((template) => (
                    <Card key={template.id}>
                        <div className={styles.cardTop}>
                            <CardHeader
                                header={<Text weight="semibold">{template.name}</Text>}
                                description={template.category ?? undefined}
                            />
                            <Menu>
                                <MenuTrigger disableButtonEnhancement>
                                    <Button appearance="transparent" icon={<MoreHorizontalRegular />} />
                                </MenuTrigger>
                                <MenuPopover>
                                    <MenuList>
                                        <MenuItem
                                            icon={<ArchiveRegular />}
                                            onClick={() => handleArchive(template.id)}
                                        >
                                            {t.templates.archive}
                                        </MenuItem>
                                        <MenuItem
                                            icon={<DeleteRegular />}
                                            onClick={() => handleDelete(template.id)}
                                        >
                                            {t.templates.delete}
                                        </MenuItem>
                                    </MenuList>
                                </MenuPopover>
                            </Menu>
                        </div>
                        <CardPreview className={styles.preview}>
                            <div
                                style={{ padding: tokens.spacingVerticalS, fontSize: '10px', overflow: 'hidden' }}
                                dangerouslySetInnerHTML={{ __html: template.html_content }}
                            />
                        </CardPreview>
                        <Text size={200}>{t.templates.usage.replace('%d', String(template.usage_count))}</Text>
                        {template.is_archived && <Badge color="subtle">{t.templates.archived}</Badge>}
                    </Card>
                ))}
            </div>
            <TemplateFormDialog open={dialogOpen} onOpenChange={setDialogOpen} onSubmit={handleCreate} />
        </AppShell>
    );
}
