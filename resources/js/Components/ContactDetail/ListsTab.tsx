import { useEffect, useState } from 'react';
import { Button, Dropdown, MessageBar, Option, Text, makeStyles, tokens } from '@fluentui/react-components';
import { ListRegular } from '@fluentui/react-icons';
import { EmptyState } from '@/Components/Shell/EmptyState';
import { useText } from '@/Hooks/useText';
import { changeListMembership, fetchAllLists, type ContactDetailData } from '@/Lib/api/contactDetail';
import type { ContactList } from '@/Lib/types/lists';
import { fill, formatDate } from './format';

const useStyles = makeStyles({
    adder: { display: 'flex', gap: tokens.spacingHorizontalS, marginBottom: tokens.spacingVerticalM, alignItems: 'center' },
    row: {
        display: 'flex',
        justifyContent: 'space-between',
        alignItems: 'center',
        padding: `${tokens.spacingVerticalS} 0`,
        borderBottom: `${tokens.strokeWidthThin} solid ${tokens.colorNeutralStroke2}`,
    },
});

interface Props {
    contact: ContactDetailData;
    canManage: boolean;
    onChanged: () => void;
}

export function ListsTab({ contact, canManage, onChanged }: Props) {
    const t = useText();
    const styles = useStyles();
    const [all, setAll] = useState<ContactList[]>([]);
    const [selected, setSelected] = useState<ContactList | null>(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState(false);
    const memberships = contact.lists ?? [];
    const memberIds = new Set(memberships.map((m) => m.id));

    useEffect(() => {
        fetchAllLists().then(setAll).catch(() => setAll([]));
    }, []);

    const change = async (listId: string, action: 'add' | 'remove') => {
        setBusy(true);
        setError(false);
        try {
            await changeListMembership(listId, contact.id, action);
            setSelected(null);
            onChanged();
        } catch {
            setError(true);
        } finally {
            setBusy(false);
        }
    };

    return (
        <div>
            {canManage && (
                <div className={styles.adder}>
                    <Dropdown
                        placeholder={t.contactDetail.addToList}
                        value={selected?.name ?? ''}
                        selectedOptions={selected ? [selected.id] : []}
                        onOptionSelect={(_, data) => setSelected(all.find((l) => l.id === data.optionValue) ?? null)}
                    >
                        {all
                            .filter((list) => !memberIds.has(list.id))
                            .map((list) => (
                                <Option key={list.id} value={list.id}>
                                    {list.name}
                                </Option>
                            ))}
                    </Dropdown>
                    <Button appearance="primary" disabled={!selected || busy} onClick={() => selected && void change(selected.id, 'add')}>
                        {t.common.add}
                    </Button>
                </div>
            )}
            {error && <MessageBar intent="error">{t.common.genericError}</MessageBar>}
            {memberships.length === 0 ? (
                <EmptyState icon={ListRegular} title={t.contactDetail.noLists} />
            ) : (
                memberships.map((membership) => (
                    <div key={membership.id} className={styles.row}>
                        <div>
                            <Text weight="semibold">{membership.name}</Text>
                            <br />
                            <Text size={200}>{fill(t.contactDetail.addedOn, formatDate(membership.added_at))}</Text>
                        </div>
                        {canManage && (
                            <Button size="small" disabled={busy} onClick={() => void change(membership.id, 'remove')}>
                                {t.contactDetail.removeFromList}
                            </Button>
                        )}
                    </div>
                ))
            )}
        </div>
    );
}
