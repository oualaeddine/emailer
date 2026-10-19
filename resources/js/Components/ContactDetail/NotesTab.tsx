import { useEffect, useState } from 'react';
import { Button, MessageBar, Text, Textarea, makeStyles, tokens } from '@fluentui/react-components';
import { NoteRegular } from '@fluentui/react-icons';
import { EmptyState } from '@/Components/Shell/EmptyState';
import { useText } from '@/Hooks/useText';
import { addNote, fetchNotes } from '@/Lib/api/contactDetail';
import type { RecipientNote } from '@/Lib/types/recipients';
import { formatDateTime } from './format';

const useStyles = makeStyles({
    composer: { display: 'flex', flexDirection: 'column', gap: tokens.spacingVerticalS, marginBottom: tokens.spacingVerticalM, maxWidth: '560px' },
    note: {
        padding: `${tokens.spacingVerticalS} 0`,
        borderBottom: `${tokens.strokeWidthThin} solid ${tokens.colorNeutralStroke2}`,
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalXXS,
    },
    body: { whiteSpace: 'pre-wrap' },
});

interface Props {
    contactId: string;
    canAnnotate: boolean;
}

export function NotesTab({ contactId, canAnnotate }: Props) {
    const t = useText();
    const styles = useStyles();
    const [notes, setNotes] = useState<RecipientNote[]>([]);
    const [draft, setDraft] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState(false);

    useEffect(() => {
        fetchNotes(contactId).then(setNotes).catch(() => setError(true));
    }, [contactId]);

    const submit = async () => {
        if (draft.trim() === '') return;
        setBusy(true);
        setError(false);
        try {
            const note = await addNote(contactId, draft.trim());
            setNotes((current) => [note, ...current]);
            setDraft('');
        } catch {
            setError(true);
        } finally {
            setBusy(false);
        }
    };

    return (
        <div>
            {canAnnotate && (
                <div className={styles.composer}>
                    <Textarea value={draft} onChange={(_, d) => setDraft(d.value)} placeholder={t.contactDetail.addNote} resize="vertical" />
                    <div>
                        <Button appearance="primary" disabled={busy || draft.trim() === ''} onClick={() => void submit()}>
                            {t.contactDetail.addNote}
                        </Button>
                    </div>
                </div>
            )}
            {error && <MessageBar intent="error">{t.common.genericError}</MessageBar>}
            {notes.length === 0 ? (
                <EmptyState icon={NoteRegular} title={t.contactDetail.notesEmpty} />
            ) : (
                notes.map((note) => (
                    <div key={note.id} className={styles.note}>
                        <Text size={200}>
                            {note.author ?? t.common.emptyValue} · {formatDateTime(note.created_at)}
                        </Text>
                        <Text className={styles.body}>{note.body}</Text>
                    </div>
                ))
            )}
        </div>
    );
}
