import { useEffect, useMemo, useState } from 'react';
import {
    Button,
    Caption1,
    Input,
    Popover,
    PopoverSurface,
    PopoverTrigger,
    Spinner,
    Tab,
    TabList,
    Text,
    makeStyles,
    tokens,
} from '@fluentui/react-components';
import { PeopleListRegular, SearchRegular } from '@fluentui/react-icons';
import { useText } from '@/Hooks/useText';
import { fetchContactLists, fetchListFolders, fetchSegments } from '@/Lib/api/contacts';
import type { Folder, Uuid } from '@/Lib/types/common';

const useStyles = makeStyles({
    surface: {
        width: '360px',
        maxWidth: 'calc(100vw - 32px)',
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalS,
    },
    list: {
        maxHeight: '320px',
        overflowY: 'auto',
        display: 'flex',
        flexDirection: 'column',
    },
    folder: {
        paddingTop: tokens.spacingVerticalS,
        paddingBottom: tokens.spacingVerticalXXS,
        color: tokens.colorNeutralForeground3,
        textTransform: 'uppercase',
    },
    item: {
        justifyContent: 'space-between',
        width: '100%',
        fontWeight: tokens.fontWeightRegular,
    },
    count: {
        color: tokens.colorNeutralForeground3,
        marginLeft: tokens.spacingHorizontalS,
    },
});

export interface ContactScopeTarget {
    type: 'list' | 'segment';
    id: Uuid;
    name: string;
}

export interface LoadListOrSegmentPopoverProps {
    /** Currently loaded list / segment (highlighted). */
    value: ContactScopeTarget | null;
    onSelect: (target: ContactScopeTarget) => void;
}

interface PickerItem {
    id: Uuid;
    name: string;
    folder_id: Uuid | null;
    count: number | null;
}

/**
 * docs/43-brevo-parity-plan.md §43.4.6 — *Charger une liste ou un segment*:
 * popover with Listes / Segments tabs (grouped by folder, searchable) that
 * scopes the Contacts table through `list_id` / `segment_id` (§45.5.4).
 */
export function LoadListOrSegmentPopover({ value, onSelect }: LoadListOrSegmentPopoverProps) {
    const styles = useStyles();
    const t = useText();
    const [open, setOpen] = useState(false);
    const [tab, setTab] = useState<'list' | 'segment'>(value?.type ?? 'list');
    const [query, setQuery] = useState('');
    const [items, setItems] = useState<PickerItem[]>([]);
    const [folders, setFolders] = useState<Folder[]>([]);
    const [loading, setLoading] = useState(false);
    const [failed, setFailed] = useState(false);

    useEffect(() => {
        if (!open) {
            return;
        }
        const controller = new AbortController();
        const timer = window.setTimeout(async () => {
            setLoading(true);
            setFailed(false);
            try {
                const [rows, folderRows] = await Promise.all([
                    tab === 'list'
                        ? fetchContactLists({ q: query || undefined, sort: 'name', per_page: 100 }, controller.signal).then((r) =>
                              r.data.map<PickerItem>((list) => ({
                                  id: list.id,
                                  name: list.name,
                                  folder_id: list.folder_id,
                                  count: list.contact_count,
                              })),
                          )
                        : fetchSegments({ q: query || undefined, sort: 'name', per_page: 100 }, controller.signal).then((r) =>
                              r.data.map<PickerItem>((segment) => ({
                                  id: segment.id,
                                  name: segment.name,
                                  folder_id: segment.folder_id,
                                  count: segment.contact_count,
                              })),
                          ),
                    fetchListFolders(tab, controller.signal).catch(() => [] as Folder[]),
                ]);
                setItems(rows);
                setFolders(folderRows);
            } catch {
                if (!controller.signal.aborted) {
                    setItems([]);
                    setFailed(true);
                }
            } finally {
                if (!controller.signal.aborted) {
                    setLoading(false);
                }
            }
        }, 250);

        return () => {
            controller.abort();
            window.clearTimeout(timer);
        };
    }, [open, tab, query]);

    const groups = useMemo(() => {
        const byFolder = new Map<string, PickerItem[]>();
        for (const item of items) {
            const key = item.folder_id ?? '';
            byFolder.set(key, [...(byFolder.get(key) ?? []), item]);
        }
        const named = folders
            .filter((folder) => byFolder.has(folder.id))
            .map((folder) => ({ id: folder.id, name: folder.name, items: byFolder.get(folder.id) ?? [] }));
        const knownIds = new Set(folders.map((folder) => folder.id));
        const orphans = items.filter((item) => !item.folder_id || !knownIds.has(item.folder_id));

        return orphans.length > 0 ? [...named, { id: '', name: t.common.noFolder, items: orphans }] : named;
    }, [items, folders, t.common.noFolder]);

    return (
        <Popover open={open} onOpenChange={(_, data) => setOpen(data.open)} positioning="below-start" trapFocus>
            <PopoverTrigger disableButtonEnhancement>
                <Button icon={<PeopleListRegular />} appearance={value ? 'primary' : 'secondary'}>
                    {t.contacts.loadListOrSegment}
                </Button>
            </PopoverTrigger>
            <PopoverSurface className={styles.surface}>
                <TabList
                    selectedValue={tab}
                    onTabSelect={(_, data) => {
                        setTab(data.value as 'list' | 'segment');
                        setItems([]);
                    }}
                    size="small"
                >
                    <Tab value="list">{t.nav.lists}</Tab>
                    <Tab value="segment">{t.nav.segments}</Tab>
                </TabList>
                <Input
                    contentBefore={<SearchRegular />}
                    placeholder={t.common.searchPlaceholder}
                    aria-label={t.common.search}
                    value={query}
                    onChange={(_, data) => setQuery(data.value)}
                />
                <div className={styles.list} role="listbox" aria-label={tab === 'list' ? t.nav.lists : t.nav.segments}>
                    {loading && items.length === 0 ? (
                        <Spinner size="tiny" label={t.common.loading} />
                    ) : failed ? (
                        <Text>{t.common.genericError}</Text>
                    ) : items.length === 0 ? (
                        <Text>{t.common.noResults}</Text>
                    ) : (
                        groups.map((group) => (
                            <div key={group.id || 'none'}>
                                {(groups.length > 1 || group.id) && <Caption1 className={styles.folder}>{group.name}</Caption1>}
                                {group.items.map((item) => (
                                    <Button
                                        key={item.id}
                                        role="option"
                                        aria-selected={value?.type === tab && value.id === item.id}
                                        appearance={value?.type === tab && value.id === item.id ? 'secondary' : 'subtle'}
                                        className={styles.item}
                                        onClick={() => {
                                            onSelect({ type: tab, id: item.id, name: item.name });
                                            setOpen(false);
                                        }}
                                    >
                                        <span>{item.name}</span>
                                        {item.count !== null && <Caption1 className={styles.count}>{item.count.toLocaleString('fr-FR')}</Caption1>}
                                    </Button>
                                ))}
                            </div>
                        ))
                    )}
                </div>
            </PopoverSurface>
        </Popover>
    );
}
