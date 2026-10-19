import { useEffect, useMemo, useState } from 'react';
import {
    Button,
    Combobox,
    Dialog,
    DialogActions,
    DialogBody,
    DialogContent,
    DialogSurface,
    DialogTitle,
    Dropdown,
    Field,
    Input,
    MessageBar,
    MessageBarBody,
    Option,
    Text,
    makeStyles,
    tokens,
} from '@fluentui/react-components';
import { useText } from '@/Hooks/useText';
import { fetchContactLists } from '@/Lib/api/contacts';
import type { ContactAttribute, ContactAttributeValue } from '@/Lib/types/attributes';
import type { ContactBulkAction, ContactBulkParams } from '@/Lib/types/contacts';
import type { ContactList } from '@/Lib/types/lists';
import { formatText } from '@/Components/Contacts/contactColumns';

const useStyles = makeStyles({
    content: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalM,
    },
});

export interface BulkActionDialogProps {
    /** Action to configure / confirm; `null` closes the dialog. */
    action: ContactBulkAction | null;
    /** Number of contacts designated by the selection. */
    count: number;
    /** GET /contact-attributes rows (for *Mettre à jour un attribut*). */
    attributes: ContactAttribute[];
    onCancel: () => void;
    /** Runs POST /recipients/bulk; reject with a string to show it in the dialog. */
    onConfirm: (params: ContactBulkParams) => Promise<void>;
}

/** Attributes that cannot be bulk-updated (identifier). */
const NON_EDITABLE_ATTRIBUTES = ['EMAIL'];

/**
 * docs/43-brevo-parity-plan.md §43.4.6 / docs/45 §45.5.4 — parameters and
 * confirmation of one bulk action (list picker, attribute + typed value,
 * blocklist / delete confirmation).
 */
export function BulkActionDialog({ action, count, attributes, onCancel, onConfirm }: BulkActionDialogProps) {
    const styles = useStyles();
    const t = useText();
    const [listQuery, setListQuery] = useState('');
    const [lists, setLists] = useState<ContactList[]>([]);
    const [listId, setListId] = useState<string | null>(null);
    const [attributeName, setAttributeName] = useState<string | null>(null);
    const [rawValue, setRawValue] = useState<string>('');
    const [multiValue, setMultiValue] = useState<string[]>([]);
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const needsList = action === 'add_to_list' || action === 'remove_from_list';

    useEffect(() => {
        if (action) {
            setListQuery('');
            setListId(null);
            setAttributeName(null);
            setRawValue('');
            setMultiValue([]);
            setError(null);
        }
    }, [action]);

    useEffect(() => {
        if (!needsList) {
            return;
        }
        const controller = new AbortController();
        const timer = window.setTimeout(() => {
            fetchContactLists({ q: listQuery || undefined, sort: 'name', per_page: 50 }, controller.signal)
                .then((response) => setLists(response.data))
                .catch(() => {
                    if (!controller.signal.aborted) {
                        setLists([]);
                    }
                });
        }, 250);

        return () => {
            controller.abort();
            window.clearTimeout(timer);
        };
    }, [needsList, listQuery]);

    const editableAttributes = useMemo(
        () => attributes.filter((attribute) => !NON_EDITABLE_ATTRIBUTES.includes(attribute.name)),
        [attributes],
    );
    const attribute = editableAttributes.find((candidate) => candidate.name === attributeName) ?? null;

    function encodedValue(): ContactAttributeValue {
        if (!attribute) {
            return null;
        }
        switch (attribute.type) {
            case 'multiple_choice':
                return multiValue.length > 0 ? multiValue : null;
            case 'number':
                return rawValue.trim() === '' ? null : Number(rawValue);
            case 'boolean':
                return rawValue === '' ? null : rawValue === 'true';
            default:
                return rawValue.trim() === '' ? null : rawValue.trim();
        }
    }

    const ready = needsList ? listId !== null : action === 'update_attribute' ? attribute !== null : action !== null;

    async function confirm() {
        if (!action || !ready) {
            return;
        }
        const params: ContactBulkParams = {};
        if (needsList && listId) {
            params.list_id = listId;
        }
        if (action === 'update_attribute' && attribute) {
            params.attribute = attribute.name;
            params.value = encodedValue();
        }
        if (action === 'blocklist') {
            params.reason = 'manual_block';
        }
        setSubmitting(true);
        setError(null);
        try {
            await onConfirm(params);
        } catch (reason) {
            setError(typeof reason === 'string' ? reason : t.common.genericError);
        } finally {
            setSubmitting(false);
        }
    }

    function renderValueEditor() {
        if (!attribute) {
            return null;
        }
        switch (attribute.type) {
            case 'boolean':
                return (
                    <Dropdown
                        value={rawValue === '' ? '' : rawValue === 'true' ? t.common.yes : t.common.no}
                        selectedOptions={rawValue === '' ? [] : [rawValue]}
                        onOptionSelect={(_, data) => setRawValue(data.optionValue ?? '')}
                    >
                        <Option value="true">{t.common.yes}</Option>
                        <Option value="false">{t.common.no}</Option>
                    </Dropdown>
                );
            case 'category':
                return (
                    <Dropdown
                        value={attribute.options.find((option) => option.value === rawValue)?.label ?? ''}
                        selectedOptions={rawValue === '' ? [] : [rawValue]}
                        onOptionSelect={(_, data) => setRawValue(data.optionValue ?? '')}
                    >
                        {attribute.options.map((option) => (
                            <Option key={option.value} value={option.value}>
                                {option.label}
                            </Option>
                        ))}
                    </Dropdown>
                );
            case 'multiple_choice':
                return (
                    <Dropdown
                        multiselect
                        value={attribute.options
                            .filter((option) => multiValue.includes(option.value))
                            .map((option) => option.label)
                            .join(', ')}
                        selectedOptions={multiValue}
                        onOptionSelect={(_, data) => setMultiValue(data.selectedOptions)}
                    >
                        {attribute.options.map((option) => (
                            <Option key={option.value} value={option.value}>
                                {option.label}
                            </Option>
                        ))}
                    </Dropdown>
                );
            case 'number':
                return <Input type="number" value={rawValue} onChange={(_, data) => setRawValue(data.value)} />;
            case 'date':
                return <Input type="date" value={rawValue} onChange={(_, data) => setRawValue(data.value)} />;
            default:
                return <Input value={rawValue} onChange={(_, data) => setRawValue(data.value)} />;
        }
    }

    const selectedList = lists.find((list) => list.id === listId);

    return (
        <Dialog open={action !== null} onOpenChange={(_, data) => !data.open && onCancel()}>
            <DialogSurface>
                <DialogBody>
                    <DialogTitle>{action ? t.contacts.bulkActions[action] : ''}</DialogTitle>
                    <DialogContent className={styles.content}>
                        <Text>{formatText(t.contacts.bulkBar, count)}</Text>
                        {needsList && (
                            <Field label={t.contacts.bulkChooseList} required>
                                <Combobox
                                    freeform
                                    placeholder={t.common.searchPlaceholder}
                                    value={listId ? selectedList?.name ?? listQuery : listQuery}
                                    selectedOptions={listId ? [listId] : []}
                                    onChange={(event) => {
                                        setListQuery(event.target.value);
                                        setListId(null);
                                    }}
                                    onOptionSelect={(_, data) => {
                                        setListId(data.optionValue ?? null);
                                        setListQuery(data.optionText ?? '');
                                    }}
                                >
                                    {lists.length === 0 ? (
                                        <Option disabled value="">
                                            {t.common.noResults}
                                        </Option>
                                    ) : (
                                        lists.map((list) => (
                                            <Option key={list.id} value={list.id} text={list.name}>
                                                {list.name}
                                            </Option>
                                        ))
                                    )}
                                </Combobox>
                            </Field>
                        )}
                        {action === 'update_attribute' && (
                            <>
                                <Field label={t.contacts.bulkChooseAttribute} required>
                                    <Dropdown
                                        value={attribute?.label ?? ''}
                                        selectedOptions={attributeName ? [attributeName] : []}
                                        onOptionSelect={(_, data) => {
                                            setAttributeName(data.optionValue ?? null);
                                            setRawValue('');
                                            setMultiValue([]);
                                        }}
                                    >
                                        {editableAttributes.map((candidate) => (
                                            <Option key={candidate.name} value={candidate.name} text={candidate.label || candidate.name}>
                                                {candidate.label || candidate.name}
                                            </Option>
                                        ))}
                                    </Dropdown>
                                </Field>
                                {attribute && <Field label={t.contacts.bulkNewValue}>{renderValueEditor()}</Field>}
                            </>
                        )}
                        {action === 'blocklist' && <Text>{formatText(t.contacts.bulkConfirmBlocklist, count)}</Text>}
                        {action === 'delete' && (
                            <>
                                <Text weight="semibold">{formatText(t.contacts.bulkConfirmDelete, count)}</Text>
                                <Text>{t.common.deleteConfirmBody}</Text>
                            </>
                        )}
                        {error && (
                            <MessageBar intent="error">
                                <MessageBarBody>{error}</MessageBarBody>
                            </MessageBar>
                        )}
                    </DialogContent>
                    <DialogActions>
                        <Button appearance="secondary" onClick={onCancel} disabled={submitting}>
                            {t.common.cancel}
                        </Button>
                        <Button appearance="primary" onClick={() => void confirm()} disabled={!ready || submitting}>
                            {submitting ? t.common.loading : action === 'delete' ? t.common.delete : t.common.confirm}
                        </Button>
                    </DialogActions>
                </DialogBody>
            </DialogSurface>
        </Dialog>
    );
}
