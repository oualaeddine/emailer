import { useState } from 'react';
import { Badge, Card, Link as FluentLink, MessageBar, Switch, Text, makeStyles, tokens } from '@fluentui/react-components';
import { useText } from '@/Hooks/useText';
import { setBlocklisted, type ContactDetailData } from '@/Lib/api/contactDetail';
import type { ContactCounters, ContactSubscriptionStatus } from '@/Lib/types/contacts';
import { formatDateTime } from './format';

const useStyles = makeStyles({
    card: { width: '300px', flex: '0 0 auto', display: 'flex', flexDirection: 'column', gap: tokens.spacingVerticalS, padding: tokens.spacingVerticalL },
    field: { display: 'flex', flexDirection: 'column' },
    label: { color: tokens.colorNeutralForeground3 },
    counters: { display: 'grid', gridTemplateColumns: '1fr 1fr', gap: tokens.spacingVerticalS, marginTop: tokens.spacingVerticalS },
});

interface Props {
    contact: ContactDetailData;
    counters: ContactCounters | null;
    canManage: boolean;
    onChanged: () => void;
}

function subscriptionOf(contact: ContactDetailData, blocklisted: boolean): ContactSubscriptionStatus {
    if (blocklisted) return 'blocklisted';

    return contact.subscription_status && contact.subscription_status !== 'blocklisted' ? contact.subscription_status : 'subscribed';
}

export function IdentityCard({ contact, counters, canManage, onChanged }: Props) {
    const t = useText();
    const styles = useStyles();
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState(false);
    const blocklisted = contact.blocklisted ?? contact.subscription_status === 'blocklisted';
    const status = subscriptionOf(contact, blocklisted);
    const name = [contact.first_name, contact.last_name].filter(Boolean).join(' ');

    const toggle = async (next: boolean) => {
        setBusy(true);
        setError(false);
        try {
            await setBlocklisted(contact.email, next);
            onChanged();
        } catch {
            setError(true);
        } finally {
            setBusy(false);
        }
    };

    return (
        <Card className={styles.card}>
            <Text weight="semibold" size={500}>
                {contact.email}
            </Text>
            <div className={styles.field}>
                <Text className={styles.label} size={200}>{t.common.name}</Text>
                <Text>{name || t.common.emptyValue}</Text>
            </div>
            <div className={styles.field}>
                <Text className={styles.label} size={200}>{t.contacts.columns.company_name}</Text>
                <Text>{contact.company_name || t.common.emptyValue}</Text>
            </div>
            {contact.pagejaunes_external_id != null && (
                <FluentLink href={`/contacts/pagejaunes?company=${contact.pagejaunes_external_id}`}>
                    {t.contactDetail.pagejaunesLink}
                </FluentLink>
            )}
            <div className={styles.field}>
                <Text className={styles.label} size={200}>{t.contactDetail.subscriptionLabel}</Text>
                <span>
                    <Badge appearance="tint" color={status === 'subscribed' ? 'success' : 'danger'}>
                        {t.contactDetail.subscriptionStatus[status]}
                    </Badge>
                </span>
            </div>
            <Switch
                label={t.contactDetail.blocklistToggle}
                checked={blocklisted}
                disabled={busy || !canManage}
                onChange={(_, data) => void toggle(data.checked)}
            />
            <Text size={200}>{blocklisted ? t.contactDetail.blocklisted : t.contactDetail.notBlocklisted}</Text>
            {error && <MessageBar intent="error">{t.common.genericError}</MessageBar>}
            <div className={styles.field}>
                <Text className={styles.label} size={200}>{t.contactDetail.createdAt}</Text>
                <Text>{formatDateTime(contact.created_at)}</Text>
            </div>
            <div className={styles.field}>
                <Text className={styles.label} size={200}>{t.contactDetail.updatedAt}</Text>
                <Text>{formatDateTime(contact.updated_at)}</Text>
            </div>
            {counters && (
                <div className={styles.counters}>
                    {(Object.keys(counters) as Array<keyof ContactCounters>).map((key) => (
                        <div key={key} className={styles.field}>
                            <Text weight="semibold">{counters[key]}</Text>
                            <Text className={styles.label} size={200}>{t.contactDetail.counters[key]}</Text>
                        </div>
                    ))}
                </div>
            )}
        </Card>
    );
}
