import { useMemo, type KeyboardEvent, type ReactNode } from 'react';
import {
    Badge,
    Button,
    Checkbox,
    DataGrid,
    DataGridBody,
    DataGridCell,
    DataGridHeader,
    DataGridHeaderCell,
    DataGridRow,
    Text,
    createTableColumn,
    makeStyles,
    tokens,
    type TableColumnDefinition,
    type TableColumnSizingOptions,
} from '@fluentui/react-components';
import { ArrowDownRegular, ArrowUpRegular } from '@fluentui/react-icons';
import { useText } from '@/Hooks/useText';
import type { ContactAttributeValue } from '@/Lib/types/attributes';
import type { Contact, ContactColumn, ContactColumnKey } from '@/Lib/types/contacts';
import {
    DEFAULT_COLUMN_WIDTH,
    describeColumn,
    formatSort,
    parseSort,
    type ContactColumnDescriptor,
} from '@/Components/Contacts/contactColumns';

const useStyles = makeStyles({
    grid: {
        minWidth: '100%',
    },
    row: {
        cursor: 'pointer',
    },
    cellText: {
        overflow: 'hidden',
        textOverflow: 'ellipsis',
        whiteSpace: 'nowrap',
        display: 'block',
        maxWidth: '100%',
    },
    headerButton: {
        minWidth: 0,
        paddingLeft: 0,
        paddingRight: 0,
        fontWeight: tokens.fontWeightSemibold,
        justifyContent: 'flex-start',
        overflow: 'hidden',
        textOverflow: 'ellipsis',
        whiteSpace: 'nowrap',
    },
    headerLabel: {
        fontWeight: tokens.fontWeightSemibold,
        overflow: 'hidden',
        textOverflow: 'ellipsis',
        whiteSpace: 'nowrap',
    },
    selectCell: {
        display: 'flex',
        alignItems: 'center',
    },
    tags: {
        display: 'flex',
        gap: tokens.spacingHorizontalXS,
        overflow: 'hidden',
    },
});

const SELECT_COLUMN_ID = '__select';

const STATUS_COLOR: Record<string, 'success' | 'danger' | 'warning'> = {
    active: 'success',
    suppressed: 'danger',
    invalid: 'warning',
};

export interface ContactsGridProps {
    items: Contact[];
    /** Visible columns, in order (pinned e-mail column first). */
    columns: ContactColumn[];
    catalog: Map<ContactColumnKey, ContactColumnDescriptor>;
    /** list uuid → name, for the "lists" column. */
    listNames: Map<string, string>;
    /** `field` / `-field` (§45.1). */
    sort: string;
    onSortChange: (sort: string) => void;
    isSelected: (id: string) => boolean;
    onToggleRow: (id: string, selected: boolean) => void;
    onTogglePage: (selected: boolean) => void;
    onColumnResize: (key: ContactColumnKey, width: number) => void;
    onOpenContact: (contact: Contact) => void;
    /** Disables the selection checkboxes (no bulk permission). */
    selectionDisabled?: boolean;
}

/**
 * docs/43-brevo-parity-plan.md §43.4.6 — the Contacts table: Fluent v9
 * DataGrid with resizable columns, server-side sort (header buttons on
 * text / number / date columns), manual row selection (so that a row click
 * opens the contact instead of toggling it) and one cell renderer per
 * column type.
 */
export function ContactsGrid({
    items,
    columns,
    catalog,
    listNames,
    sort,
    onSortChange,
    isSelected,
    onToggleRow,
    onTogglePage,
    onColumnResize,
    onOpenContact,
    selectionDisabled = false,
}: ContactsGridProps) {
    const styles = useStyles();
    const t = useText();
    const parsedSort = parseSort(sort);

    const selectedOnPage = items.filter((item) => isSelected(item.id)).length;
    const headerChecked: boolean | 'mixed' =
        items.length > 0 && selectedOnPage === items.length ? true : selectedOnPage > 0 ? 'mixed' : false;

    const descriptors = useMemo(() => columns.map((column) => describeColumn(column.key, catalog)), [columns, catalog]);

    const sizingOptions = useMemo<TableColumnSizingOptions>(() => {
        const options: TableColumnSizingOptions = {
            [SELECT_COLUMN_ID]: { minWidth: 44, idealWidth: 44, defaultWidth: 44 },
        };
        for (const column of columns) {
            const width = column.width ?? DEFAULT_COLUMN_WIDTH;
            options[column.key] = { minWidth: 80, idealWidth: width, defaultWidth: width };
        }

        return options;
    }, [columns]);

    function toggleSort(descriptor: ContactColumnDescriptor) {
        const direction =
            parsedSort.key === descriptor.key && parsedSort.direction === 'ascending' ? 'descending' : 'ascending';
        onSortChange(formatSort({ key: descriptor.key, direction }));
    }

    function renderHeader(descriptor: ContactColumnDescriptor): ReactNode {
        if (!descriptor.sortable) {
            return <span className={styles.headerLabel}>{descriptor.label}</span>;
        }
        const active = parsedSort.key === descriptor.key;

        return (
            <Button
                appearance="transparent"
                size="small"
                className={styles.headerButton}
                icon={active ? parsedSort.direction === 'ascending' ? <ArrowUpRegular /> : <ArrowDownRegular /> : undefined}
                iconPosition="after"
                aria-sort={active ? parsedSort.direction : undefined}
                onClick={(event) => {
                    event.stopPropagation();
                    toggleSort(descriptor);
                }}
            >
                {descriptor.label}
            </Button>
        );
    }

    const gridColumns: TableColumnDefinition<Contact>[] = [
        createTableColumn<Contact>({
            columnId: SELECT_COLUMN_ID,
            renderHeaderCell: () => (
                <Checkbox
                    checked={headerChecked}
                    disabled={selectionDisabled || items.length === 0}
                    aria-label={t.common.selectAll}
                    onChange={(_, data) => onTogglePage(data.checked === true)}
                />
            ),
            renderCell: (contact) => (
                <div className={styles.selectCell} onClick={(event) => event.stopPropagation()}>
                    <Checkbox
                        checked={isSelected(contact.id)}
                        disabled={selectionDisabled}
                        aria-label={contact.email}
                        onChange={(_, data) => onToggleRow(contact.id, data.checked === true)}
                    />
                </div>
            ),
        }),
        ...descriptors.map((descriptor) =>
            createTableColumn<Contact>({
                columnId: descriptor.key,
                renderHeaderCell: () => renderHeader(descriptor),
                renderCell: (contact) => renderContactCell(contact, descriptor),
            }),
        ),
    ];

    function textCell(value: string, weight: 'regular' | 'semibold' = 'regular'): ReactNode {
        return (
            <Text className={styles.cellText} weight={weight} title={value}>
                {value}
            </Text>
        );
    }

    function renderContactCell(contact: Contact, descriptor: ContactColumnDescriptor): ReactNode {
        const empty = t.common.emptyValue;

        switch (descriptor.key) {
            case 'contact.status':
                return (
                    <Badge appearance="tint" color={STATUS_COLOR[contact.status] ?? 'informative'}>
                        {descriptor.options?.[contact.status] ?? contact.status}
                    </Badge>
                );
            case 'contact.source':
                return textCell(descriptor.options?.[contact.source] ?? contact.source);
            case 'contact.blocklisted':
                return contact.blocklisted ? (
                    <Badge appearance="tint" color="danger">
                        {t.common.yes}
                    </Badge>
                ) : (
                    textCell(t.common.no)
                );
            case 'contact.engagement_score':
                return textCell(contact.engagement_score === null ? empty : String(contact.engagement_score));
            case 'contact.created_at':
            case 'contact.updated_at':
            case 'contact.last_opened_at':
            case 'contact.last_clicked_at':
            case 'contact.opt_in_at':
            case 'contact.blocklisted_at': {
                const field = descriptor.key.slice('contact.'.length) as
                    | 'created_at'
                    | 'updated_at'
                    | 'last_opened_at'
                    | 'last_clicked_at'
                    | 'opt_in_at'
                    | 'blocklisted_at';

                return textCell(formatDateTime(contact[field]) ?? empty);
            }
            case 'lists': {
                const names = (contact.list_ids ?? []).map((id) => listNames.get(id) ?? id);

                return textCell(names.length > 0 ? names.join(', ') : empty);
            }
            case 'tags':
                return contact.tags.length > 0 ? (
                    <span className={styles.tags}>
                        {contact.tags.map((tag) => (
                            <Badge key={tag.id} appearance="outline" size="small">
                                {tag.name}
                            </Badge>
                        ))}
                    </span>
                ) : (
                    textCell(empty)
                );
            default:
                break;
        }

        if (descriptor.key.startsWith('attribute.')) {
            const name = descriptor.key.slice('attribute.'.length);
            const value = attributeValue(contact, name);
            const formatted = formatAttributeValue(value, descriptor) ?? empty;

            return textCell(formatted, descriptor.key === 'attribute.EMAIL' ? 'semibold' : 'regular');
        }

        return textCell(empty);
    }

    function formatAttributeValue(value: ContactAttributeValue | undefined, descriptor: ContactColumnDescriptor): string | null {
        if (value === null || value === undefined || value === '') {
            return null;
        }
        switch (descriptor.type) {
            case 'boolean':
                return value === true || value === 'true' || value === 1 ? t.common.yes : t.common.no;
            case 'date':
                return typeof value === 'string' ? formatDate(value) : String(value);
            case 'number':
                return typeof value === 'number' ? value.toLocaleString('fr-FR') : String(value);
            case 'category':
                return descriptor.options?.[String(value)] ?? String(value);
            case 'multiple_choice': {
                const values = Array.isArray(value) ? value : [String(value)];

                return values.map((item) => descriptor.options?.[item] ?? item).join(', ');
            }
            default:
                return Array.isArray(value) ? value.join(', ') : String(value);
        }
    }

    return (
        <DataGrid
            items={items}
            columns={gridColumns}
            getRowId={(contact: Contact) => contact.id}
            resizableColumns
            resizableColumnsOptions={{ autoFitColumns: false }}
            columnSizingOptions={sizingOptions}
            onColumnResize={(_, data) => {
                if (data.columnId !== SELECT_COLUMN_ID) {
                    onColumnResize(data.columnId as ContactColumnKey, Math.round(data.width));
                }
            }}
            className={styles.grid}
        >
            <DataGridHeader>
                <DataGridRow>{({ renderHeaderCell }) => <DataGridHeaderCell>{renderHeaderCell()}</DataGridHeaderCell>}</DataGridRow>
            </DataGridHeader>
            <DataGridBody<Contact>>
                {({ item, rowId }) => (
                    <DataGridRow<Contact>
                        key={rowId}
                        className={styles.row}
                        onClick={() => onOpenContact(item)}
                        onKeyDown={(event: KeyboardEvent<HTMLDivElement>) => {
                            if (event.key === 'Enter' && !(event.target instanceof HTMLInputElement)) {
                                onOpenContact(item);
                            }
                        }}
                    >
                        {({ renderCell }) => <DataGridCell>{renderCell(item)}</DataGridCell>}
                    </DataGridRow>
                )}
            </DataGridBody>
        </DataGrid>
    );
}

/** System attributes stored in recipients columns are also exposed as top-level row fields. */
function attributeValue(contact: Contact, name: string): ContactAttributeValue | undefined {
    const fromAttributes = contact.attributes?.[name];
    if (fromAttributes !== undefined && fromAttributes !== null) {
        return fromAttributes;
    }
    switch (name) {
        case 'EMAIL':
            return contact.email;
        case 'PRENOM':
            return contact.first_name;
        case 'NOM':
            return contact.last_name;
        case 'SOCIETE':
            return contact.company_name;
        case 'EXT_ID':
            return contact.ext_id;
        default:
            return fromAttributes;
    }
}

function formatDate(value: string): string {
    const date = /^\d{4}-\d{2}-\d{2}$/.test(value) ? new Date(`${value}T00:00:00`) : new Date(value);

    return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString('fr-FR');
}

function formatDateTime(value: string | null): string | null {
    if (!value) {
        return null;
    }
    const date = new Date(value);

    return Number.isNaN(date.getTime())
        ? value
        : date.toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' });
}
