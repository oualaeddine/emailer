import { useEffect, useState } from 'react';
import { Dropdown, Field, Link, MessageBar, MessageBarBody, Option, Spinner } from '@fluentui/react-components';
import { useText } from '@/Hooks/useText';
import { fetchSenders } from '@/Lib/api/senders';
import type { Sender } from '@/Lib/types/senders';
import { VerifiedBadge } from '@/Components/Senders/StatusBadges';

export interface SenderPickerProps {
    value: string | null;
    onChange: (senderId: string | null, sender: Sender | null) => void;
    verifiedOnly?: boolean;
    disabled?: boolean;
    label?: string;
}

/** docs/44 WP-41U contract — sender dropdown shared by campaigns and composer. */
export default function SenderPicker({ value, onChange, verifiedOnly = true, disabled, label }: SenderPickerProps) {
    const t = useText();
    const [senders, setSenders] = useState<Sender[]>([]);
    const [loading, setLoading] = useState(true);
    const [failed, setFailed] = useState(false);

    useEffect(() => {
        let cancelled = false;
        setLoading(true);
        setFailed(false);
        fetchSenders(verifiedOnly === false ? {} : { verified: 1 })
            .then((rows) => {
                if (!cancelled) {
                    setSenders(rows);
                }
            })
            .catch(() => {
                if (!cancelled) {
                    setFailed(true);
                }
            })
            .finally(() => {
                if (!cancelled) {
                    setLoading(false);
                }
            });

        return () => {
            cancelled = true;
        };
    }, [verifiedOnly]);

    const selected = senders.find((s) => s.id === value) ?? null;
    const text = (s: Sender) => `${s.name} <${s.email}>`;

    return (
        <Field label={label ?? t.senders.picker}>
            {loading ? (
                <Spinner size="tiny" label={t.common.loading} />
            ) : failed ? (
                <MessageBar intent="error">
                    <MessageBarBody>{t.common.genericError}</MessageBarBody>
                </MessageBar>
            ) : (
                <>
                    {senders.length === 0 && (
                        <MessageBar intent="warning">
                            <MessageBarBody>{t.senders.emptyBody}</MessageBarBody>
                        </MessageBar>
                    )}
                    <Dropdown
                        disabled={disabled || senders.length === 0}
                        value={selected ? text(selected) : ''}
                        selectedOptions={value ? [value] : []}
                        onOptionSelect={(_, data) => {
                            const id = data.optionValue ?? null;
                            onChange(id, senders.find((s) => s.id === id) ?? null);
                        }}
                    >
                        {senders.map((s) => (
                            <Option key={s.id} value={s.id} text={text(s)}>
                                {text(s)} <VerifiedBadge verified={s.is_verified} />
                            </Option>
                        ))}
                    </Dropdown>
                </>
            )}
            <Link href="/settings/senders">{t.senders.manage}</Link>
        </Field>
    );
}
