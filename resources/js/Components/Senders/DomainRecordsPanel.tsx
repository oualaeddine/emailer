import { useState } from 'react';
import { Button, Text, makeStyles, tokens } from '@fluentui/react-components';
import { CheckmarkRegular, CopyRegular } from '@fluentui/react-icons';
import { useText } from '@/Hooks/useText';
import type { DnsRecord, SendingDomain } from '@/Lib/types/senders';
import { RecordStatusBadge } from '@/Components/Senders/StatusBadges';

const useStyles = makeStyles({
    root: { display: 'flex', flexDirection: 'column', gap: tokens.spacingVerticalM },
    record: {
        border: `1px solid ${tokens.colorNeutralStroke2}`,
        borderRadius: tokens.borderRadiusMedium,
        padding: tokens.spacingVerticalS,
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalXS,
    },
    head: { display: 'flex', alignItems: 'center', gap: tokens.spacingHorizontalS },
    row: { display: 'flex', alignItems: 'flex-start', gap: tokens.spacingHorizontalS },
    label: { minWidth: '110px', color: tokens.colorNeutralForeground3 },
    mono: { fontFamily: tokens.fontFamilyMonospace, wordBreak: 'break-all', flex: 1 },
});

function CopyRow({ label, value }: { label: string; value: string }) {
    const styles = useStyles();
    const t = useText();
    const [copied, setCopied] = useState(false);

    async function copy() {
        try {
            await navigator.clipboard.writeText(value);
            setCopied(true);
            window.setTimeout(() => setCopied(false), 2000);
        } catch {
            setCopied(false);
        }
    }

    return (
        <div className={styles.row}>
            <Text className={styles.label}>{label}</Text>
            <Text className={styles.mono}>{value}</Text>
            <Button
                size="small"
                appearance="subtle"
                icon={copied ? <CheckmarkRegular /> : <CopyRegular />}
                aria-label={`${t.domains.copyValue} (${label})`}
                title={copied ? t.common.copied : t.domains.copyValue}
                onClick={() => void copy()}
            />
        </div>
    );
}

function RecordCard({ record }: { record: DnsRecord }) {
    const styles = useStyles();
    const t = useText();

    return (
        <div className={styles.record}>
            <div className={styles.head}>
                <Text weight="semibold">{t.domains.recordKinds[record.kind]}</Text>
                <RecordStatusBadge status={record.status} />
            </div>
            <CopyRow label={t.domains.recordType} value={record.type} />
            <CopyRow label={t.domains.host} value={record.host} />
            <CopyRow label={t.domains.value} value={record.value} />
            {record.found_value && (
                <div className={styles.row}>
                    <Text className={styles.label}>{t.domains.foundValue}</Text>
                    <Text className={styles.mono}>{record.found_value}</Text>
                </div>
            )}
        </div>
    );
}

/** SPF / DKIM / DMARC records to publish, with copy buttons and per-record status. */
export function DomainRecordsPanel({ domain }: { domain: SendingDomain }) {
    const styles = useStyles();
    const t = useText();

    return (
        <div className={styles.root}>
            <Text>{t.domains.recordsHelp}</Text>
            <Text weight="semibold">{t.domains.records}</Text>
            {domain.records.map((record) => (
                <RecordCard key={record.kind} record={record} />
            ))}
        </div>
    );
}
