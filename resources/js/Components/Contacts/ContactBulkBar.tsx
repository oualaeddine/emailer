import { Button, Link, Text, makeStyles, tokens } from '@fluentui/react-components';
import {
    ArrowDownloadRegular,
    DeleteRegular,
    DismissRegular,
    EditRegular,
    PersonAvailableRegular,
    ProhibitedRegular,
    TextBulletListAddRegular,
    TextBulletListDismissRegular,
} from '@fluentui/react-icons';
import type { ReactElement } from 'react';
import { useText } from '@/Hooks/useText';
import type { ContactBulkAction } from '@/Lib/types/contacts';
import { formatText } from '@/Components/Contacts/contactColumns';

const useStyles = makeStyles({
    root: {
        display: 'flex',
        flexWrap: 'wrap',
        alignItems: 'center',
        gap: tokens.spacingHorizontalS,
        padding: `${tokens.spacingVerticalS} ${tokens.spacingHorizontalM}`,
        marginBottom: tokens.spacingVerticalS,
        borderRadius: tokens.borderRadiusMedium,
        backgroundColor: tokens.colorBrandBackground2,
    },
    summary: {
        display: 'flex',
        flexWrap: 'wrap',
        alignItems: 'center',
        gap: tokens.spacingHorizontalS,
        marginRight: 'auto',
    },
    actions: {
        display: 'flex',
        flexWrap: 'wrap',
        gap: tokens.spacingHorizontalXS,
    },
});

export type ContactBulkBarAction = ContactBulkAction | 'export';

export interface ContactBulkBarProps {
    /** Contacts currently designated (ids, or all matching minus exclusions). */
    selectedCount: number;
    /** True when the selection is "all matching" (ContactSelection.all_matching). */
    allMatching: boolean;
    /** Offer *Sélectionner les N contacts correspondants* (whole page selected, more matches exist). */
    matchingTotal: number | null;
    canSelectAllMatching: boolean;
    onSelectAllMatching: () => void;
    onClear: () => void;
    onAction: (action: ContactBulkBarAction) => void;
    /** Actions the user may run (permission-filtered by the page). */
    allowedActions: ContactBulkBarAction[];
}

const ACTION_ICONS: Record<ContactBulkBarAction, ReactElement> = {
    add_to_list: <TextBulletListAddRegular />,
    remove_from_list: <TextBulletListDismissRegular />,
    blocklist: <ProhibitedRegular />,
    unblocklist: <PersonAvailableRegular />,
    update_attribute: <EditRegular />,
    export: <ArrowDownloadRegular />,
    delete: <DeleteRegular />,
};

const ACTION_ORDER: ContactBulkBarAction[] = [
    'add_to_list',
    'remove_from_list',
    'blocklist',
    'unblocklist',
    'update_attribute',
    'export',
    'delete',
];

/** docs/43-brevo-parity-plan.md §43.4.6 — bulk-action bar shown while contacts are selected. */
export function ContactBulkBar({
    selectedCount,
    allMatching,
    matchingTotal,
    canSelectAllMatching,
    onSelectAllMatching,
    onClear,
    onAction,
    allowedActions,
}: ContactBulkBarProps) {
    const styles = useStyles();
    const t = useText();

    return (
        <div className={styles.root} role="region" aria-label={formatText(t.contacts.bulkBar, selectedCount)}>
            <div className={styles.summary}>
                <Text weight="semibold">{formatText(t.contacts.bulkBar, selectedCount)}</Text>
                {!allMatching && canSelectAllMatching && matchingTotal !== null && (
                    <Link as="button" onClick={onSelectAllMatching}>
                        {formatText(t.contacts.selectAllMatching, matchingTotal)}
                    </Link>
                )}
                <Button appearance="transparent" size="small" icon={<DismissRegular />} onClick={onClear}>
                    {t.common.clearSelection}
                </Button>
            </div>
            <div className={styles.actions}>
                {ACTION_ORDER.filter((action) => allowedActions.includes(action)).map((action) => (
                    <Button key={action} size="small" icon={ACTION_ICONS[action]} onClick={() => onAction(action)}>
                        {t.contacts.bulkActions[action]}
                    </Button>
                ))}
            </div>
        </div>
    );
}
