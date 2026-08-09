import { useState } from 'react';
import { Menu, MenuItem, MenuList, MenuPopover, MenuTrigger, ToolbarButton } from '@fluentui/react-components';
import {
    BookInformation24Regular,
    QuestionCircleFilled,
    QuestionCircleRegular,
    bundleIcon,
} from '@fluentui/react-icons';
import { router, usePage } from '@inertiajs/react';
import { useText } from '@/Hooks/useText';
import { topicForUrl } from '@/Lib/docs/registry';
import { HelpDialog } from '@/Components/Help/HelpDialog';

const QuestionCircleIcon = bundleIcon(QuestionCircleFilled, QuestionCircleRegular);

/**
 * Help entry point in the app shell toolbar. "Aide sur cette page" resolves
 * the documentation topic from the current route, so every page — including
 * pages added later — gets contextual help without touching the page itself,
 * as long as it has a registry entry with a `route`.
 */
export function HelpMenuButton() {
    const t = useText();
    const { url } = usePage();
    const [open, setOpen] = useState(false);
    const topic = topicForUrl(url);

    return (
        <>
            <Menu>
                <MenuTrigger disableButtonEnhancement>
                    <ToolbarButton icon={<QuestionCircleIcon />} aria-label={t.help.open} />
                </MenuTrigger>
                <MenuPopover>
                    <MenuList>
                        <MenuItem
                            icon={<QuestionCircleRegular />}
                            disabled={!topic}
                            onClick={() => setOpen(true)}
                        >
                            {t.help.pageHelp}
                        </MenuItem>
                        <MenuItem
                            icon={<BookInformation24Regular />}
                            onClick={() => router.visit('/help')}
                        >
                            {t.help.docsCenter}
                        </MenuItem>
                    </MenuList>
                </MenuPopover>
            </Menu>
            {topic && <HelpDialog slug={topic.slug} open={open} onOpenChange={setOpen} />}
        </>
    );
}
