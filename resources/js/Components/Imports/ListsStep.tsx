import { useEffect, useState } from 'react';
import { Button, Checkbox, Field, Input, Spinner, Text, makeStyles, tokens } from '@fluentui/react-components';
import { useText } from '@/Hooks/useText';
import { fetchRecipientLists } from '@/Lib/api/imports';
import type { ContactList } from '@/Lib/types/lists';

const useStyles = makeStyles({
    root: { display: 'flex', flexDirection: 'column', gap: tokens.spacingVerticalM, maxWidth: '560px' },
    list: { display: 'flex', flexDirection: 'column', maxHeight: '320px', overflowY: 'auto' },
    actions: { display: 'flex', gap: tokens.spacingHorizontalS },
});

interface Props {
    listIds: string[];
    newListName: string;
    onChange: (listIds: string[], newListName: string) => void;
    onBack: () => void;
    onNext: () => void;
}

/** Step 3 — Listes: add to existing lists and/or create a new one. */
export function ListsStep({ listIds, newListName, onChange, onBack, onNext }: Props) {
    const styles = useStyles();
    const t = useText();
    const [lists, setLists] = useState<ContactList[] | null>(null);
    const [q, setQ] = useState('');

    useEffect(() => {
        const handle = window.setTimeout(() => {
            fetchRecipientLists(q)
                .then(setLists)
                .catch(() => setLists([]));
        }, 250);

        return () => window.clearTimeout(handle);
    }, [q]);

    const valid = listIds.length > 0 || newListName.trim() !== '';

    return (
        <div className={styles.root}>
            <Field label={t.imports.wizard.addToLists}>
                <Input placeholder={t.common.searchPlaceholder} value={q} onChange={(_, d) => setQ(d.value)} />
            </Field>
            {lists === null && <Spinner label={t.common.loading} />}
            <div className={styles.list}>
                {(lists ?? []).map((list) => (
                    <Checkbox
                        key={list.id}
                        checked={listIds.includes(list.id)}
                        label={`${list.name} (${list.contact_count})`}
                        onChange={(_, d) =>
                            onChange(d.checked ? [...listIds, list.id] : listIds.filter((id) => id !== list.id), newListName)
                        }
                    />
                ))}
            </div>
            <Field label={t.imports.wizard.createList}>
                <Input
                    placeholder={t.imports.wizard.newListName}
                    value={newListName}
                    onChange={(_, d) => onChange(listIds, d.value)}
                />
            </Field>
            {!valid && <Text>{t.imports.wizard.noListSelected}</Text>}
            <div className={styles.actions}>
                <Button onClick={onBack}>{t.common.back}</Button>
                <Button appearance="primary" disabled={!valid} onClick={onNext}>
                    {t.common.next}
                </Button>
            </div>
        </div>
    );
}
