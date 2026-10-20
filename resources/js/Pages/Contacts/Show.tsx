import { useCallback, useEffect, useState } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import { Card, Spinner, Tab, TabList, Text, makeStyles, tokens } from '@fluentui/react-components';
import type { SelectTabData } from '@fluentui/react-components';
import { PageHelp } from '@/Components/Help/PageHelp';
import { AppShell } from '@/Components/Shell/AppShell';
import { ActivityTimeline } from '@/Components/ContactDetail/ActivityTimeline';
import { AttributesForm } from '@/Components/ContactDetail/AttributesForm';
import { IdentityCard } from '@/Components/ContactDetail/IdentityCard';
import { ListsTab } from '@/Components/ContactDetail/ListsTab';
import { NotesTab } from '@/Components/ContactDetail/NotesTab';
import { useText } from '@/Hooks/useText';
import { fetchContactDetail, type ContactDetailData } from '@/Lib/api/contactDetail';
import { hasPermission } from '@/Lib/permissions';
import type { ContactCounters } from '@/Lib/types/contacts';

type TabKey = 'activity' | 'attributes' | 'lists' | 'notes';
const TABS: TabKey[] = ['activity', 'attributes', 'lists', 'notes'];

const useStyles = makeStyles({
    back: { display: 'inline-block', marginBottom: tokens.spacingVerticalM },
    layout: { display: 'flex', gap: tokens.spacingHorizontalL, alignItems: 'flex-start', flexWrap: 'wrap' },
    centre: { flex: '1 1 480px', minWidth: 0, padding: tokens.spacingVerticalL, display: 'flex', flexDirection: 'column', gap: tokens.spacingVerticalM },
});

interface PageProps {
    uuid: string;
    auth: { permissions: string[] };
    [key: string]: unknown;
}

export default function Show() {
    const t = useText();
    const styles = useStyles();
    const { props } = usePage<PageProps>();
    const permissions = props.auth?.permissions ?? [];
    const [contact, setContact] = useState<ContactDetailData | null>(null);
    const [counters, setCounters] = useState<ContactCounters | null>(null);
    const [failed, setFailed] = useState(false);
    const [tab, setTab] = useState<TabKey>('activity');

    const reload = useCallback(() => {
        fetchContactDetail(props.uuid)
            .then((detail) => {
                setContact(detail);
                if (detail.counters) setCounters(detail.counters);
            })
            .catch(() => setFailed(true));
    }, [props.uuid]);

    useEffect(() => {
        reload();
    }, [reload]);

    const canManage = hasPermission(permissions, 'recipients.manage');

    return (
        <AppShell>
            <Head title={contact?.email ?? t.contactDetail.title} />
            <Link href="/contacts" className={styles.back}>
                {t.contactDetail.backToContacts}
            </Link>
            <PageHelp topic="contact-detail" hideHeaderRow />

            {failed && <Text role="alert">{t.common.genericError}</Text>}
            {!contact && !failed && <Spinner label={t.common.loading} />}

            {contact && (
                <div className={styles.layout}>
                    <IdentityCard contact={contact} counters={counters} canManage={canManage} onChanged={reload} />
                    <Card className={styles.centre}>
                        <TabList selectedValue={tab} onTabSelect={(_, data: SelectTabData) => setTab(data.value as TabKey)}>
                            {TABS.map((key) => (
                                <Tab key={key} value={key}>
                                    {t.contactDetail.tabs[key]}
                                </Tab>
                            ))}
                        </TabList>
                        {tab === 'activity' && <ActivityTimeline contactId={contact.id} onCounters={setCounters} />}
                        {tab === 'attributes' && <AttributesForm contact={contact} canManage={canManage} onSaved={reload} />}
                        {tab === 'lists' && <ListsTab contact={contact} canManage={canManage} onChanged={reload} />}
                        {tab === 'notes' && <NotesTab contactId={contact.id} canAnnotate={hasPermission(permissions, 'recipients.annotate')} />}
                    </Card>
                </div>
            )}
        </AppShell>
    );
}
