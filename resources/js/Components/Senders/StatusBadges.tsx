import { Badge } from '@fluentui/react-components';
import { useText } from '@/Hooks/useText';
import type { DnsRecordStatus, SendingDomainStatus } from '@/Lib/types/senders';

type BadgeColor = 'success' | 'warning' | 'danger' | 'informative' | 'subtle';

const DOMAIN_COLOR: Record<SendingDomainStatus, BadgeColor> = {
    pending: 'subtle',
    checking: 'informative',
    verified: 'success',
    partially_verified: 'warning',
    failed: 'danger',
};

const RECORD_COLOR: Record<DnsRecordStatus, BadgeColor> = {
    pending: 'subtle',
    valid: 'success',
    invalid: 'danger',
    missing: 'warning',
};

export function DomainStatusBadge({ status }: { status: SendingDomainStatus | null }) {
    const t = useText();
    if (status === null) {
        return <>—</>;
    }

    return (
        <Badge appearance="tint" color={DOMAIN_COLOR[status]}>
            {t.domains.statusLabels[status]}
        </Badge>
    );
}

export function RecordStatusBadge({ status }: { status: DnsRecordStatus }) {
    const t = useText();

    return (
        <Badge appearance="tint" color={RECORD_COLOR[status]}>
            {t.domains.recordStatusLabels[status]}
        </Badge>
    );
}

export function VerifiedBadge({ verified }: { verified: boolean }) {
    const t = useText();

    return (
        <Badge appearance="tint" color={verified ? 'success' : 'warning'}>
            {verified ? t.senders.verified : t.senders.notVerified}
        </Badge>
    );
}
