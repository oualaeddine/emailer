import {
    Button,
    Menu,
    MenuItem,
    MenuList,
    MenuPopover,
    MenuTrigger,
    Tab,
    TabList,
    makeStyles,
    tokens,
} from '@fluentui/react-components';
import { DeleteRegular, EditRegular, MoreHorizontalRegular } from '@fluentui/react-icons';
import { useText } from '@/Hooks/useText';
import type { ContactView } from '@/Lib/types/contacts';

const useStyles = makeStyles({
    root: {
        display: 'flex',
        alignItems: 'center',
        gap: tokens.spacingHorizontalXS,
        borderBottom: `${tokens.strokeWidthThin} solid ${tokens.colorNeutralStroke2}`,
        marginBottom: tokens.spacingVerticalM,
        overflowX: 'auto',
    },
    tabs: {
        flexShrink: 0,
    },
});

export interface ContactViewTabsProps {
    /** System views ("all", "mine") first, then saved views. */
    views: ContactView[];
    activeId: string;
    onSelect: (view: ContactView) => void;
    onRename: (view: ContactView) => void;
    onDelete: (view: ContactView) => void;
}

/** French name of a view; system views always use the dictionary labels. */
export function contactViewLabel(view: Pick<ContactView, 'id' | 'name' | 'is_system'>, t: ReturnType<typeof useText>): string {
    if (view.id === 'all') {
        return t.contacts.views.all;
    }
    if (view.id === 'mine') {
        return t.contacts.views.mine;
    }

    return view.name;
}

/**
 * docs/43-brevo-parity-plan.md §43.4.6 — *Vues* as tabs: *Tous les
 * contacts*, *Mes contacts*, then saved views. Saved views get a rename /
 * delete menu; the two system views cannot be modified.
 */
export function ContactViewTabs({ views, activeId, onSelect, onRename, onDelete }: ContactViewTabsProps) {
    const styles = useStyles();
    const t = useText();
    const active = views.find((view) => view.id === activeId);

    return (
        <div className={styles.root}>
            <TabList
                className={styles.tabs}
                selectedValue={activeId}
                onTabSelect={(_, data) => {
                    const view = views.find((candidate) => candidate.id === data.value);
                    if (view) {
                        onSelect(view);
                    }
                }}
            >
                {views.map((view) => (
                    <Tab key={view.id} value={view.id}>
                        {contactViewLabel(view, t)}
                    </Tab>
                ))}
            </TabList>
            {active && !active.is_system && (
                <Menu>
                    <MenuTrigger disableButtonEnhancement>
                        <Button appearance="subtle" icon={<MoreHorizontalRegular />} aria-label={t.common.moreActions} />
                    </MenuTrigger>
                    <MenuPopover>
                        <MenuList>
                            <MenuItem icon={<EditRegular />} onClick={() => onRename(active)}>
                                {t.contacts.views.renameView}
                            </MenuItem>
                            <MenuItem icon={<DeleteRegular />} onClick={() => onDelete(active)}>
                                {t.contacts.views.deleteView}
                            </MenuItem>
                        </MenuList>
                    </MenuPopover>
                </Menu>
            )}
        </div>
    );
}
