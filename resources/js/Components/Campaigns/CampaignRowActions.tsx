import { Button, Menu, MenuDivider, MenuItem, MenuList, MenuPopover, MenuTrigger, Tooltip } from '@fluentui/react-components';
import {
    ArchiveRegular,
    ArrowUndoRegular,
    CalendarCancelRegular,
    CalendarLtrRegular,
    CopyRegular,
    DataBarVerticalRegular,
    DeleteRegular,
    DismissCircleRegular,
    EditRegular,
    FolderArrowRightRegular,
    MoreHorizontalRegular,
    PauseRegular,
    PeopleAddRegular,
    PlayRegular,
    SendRegular,
    MailArrowUpRegular,
} from '@fluentui/react-icons';
import { Fragment, type ReactElement } from 'react';
import { useText } from '@/Hooks/useText';
import type { CampaignAction, CampaignV2 } from '@/Lib/types/campaigns';

/** Menu order (Brevo "Plus d'actions", docs/43 §43.4.1); every entry is gated by `available_actions`. */
const ACTION_ORDER: CampaignAction[] = [
    'edit',
    'report',
    'duplicate',
    'test_send',
    'schedule',
    'unschedule',
    'send_now',
    'pause',
    'resume',
    'cancel',
    'send_to_new_contacts',
    'move',
    'archive',
    'unarchive',
    'delete',
];

const ACTION_ICON: Record<CampaignAction, ReactElement> = {
    edit: <EditRegular />,
    report: <DataBarVerticalRegular />,
    duplicate: <CopyRegular />,
    test_send: <MailArrowUpRegular />,
    schedule: <CalendarLtrRegular />,
    unschedule: <CalendarCancelRegular />,
    send_now: <SendRegular />,
    pause: <PauseRegular />,
    resume: <PlayRegular />,
    cancel: <DismissCircleRegular />,
    send_to_new_contacts: <PeopleAddRegular />,
    move: <FolderArrowRightRegular />,
    archive: <ArchiveRegular />,
    unarchive: <ArrowUndoRegular />,
    delete: <DeleteRegular />,
};

export interface CampaignRowActionsProps {
    campaign: CampaignV2;
    onAction: (action: CampaignAction, campaign: CampaignV2) => void;
    disabled?: boolean;
}

/** Row **Plus d'actions** menu of Campaigns/Index. */
export function CampaignRowActions({ campaign, onAction, disabled }: CampaignRowActionsProps) {
    const t = useText();
    const actions = ACTION_ORDER.filter((action) => campaign.available_actions.includes(action));
    const destructiveIndex = actions.indexOf('delete');

    return (
        <Menu positioning="below-end">
            <MenuTrigger disableButtonEnhancement>
                <Tooltip content={t.common.moreActions} relationship="label">
                    <Button
                        appearance="subtle"
                        icon={<MoreHorizontalRegular />}
                        disabled={disabled || actions.length === 0}
                        onClick={(event) => event.stopPropagation()}
                    />
                </Tooltip>
            </MenuTrigger>
            <MenuPopover>
                <MenuList>
                    {actions.map((action, index) => (
                        <Fragment key={action}>
                            {index === destructiveIndex && index > 0 && <MenuDivider />}
                            <MenuItem icon={ACTION_ICON[action]} onClick={() => onAction(action, campaign)}>
                                {t.campaigns.actions[action]}
                            </MenuItem>
                        </Fragment>
                    ))}
                </MenuList>
            </MenuPopover>
        </Menu>
    );
}
