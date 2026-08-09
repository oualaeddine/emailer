import { useState } from 'react';
import { Button, ToolbarButton, Tooltip } from '@fluentui/react-components';
import { QuestionCircleFilled, QuestionCircleRegular, bundleIcon } from '@fluentui/react-icons';
import { useText } from '@/Hooks/useText';
import { HelpDialog } from '@/Components/Help/HelpDialog';

const QuestionCircleIcon = bundleIcon(QuestionCircleFilled, QuestionCircleRegular);

interface HelpButtonProps {
    /** Registry slug (see Lib/docs/registry.ts). */
    topic: string;
    /** 'toolbar' for the app shell toolbar, 'subtle' for a dialog/drawer title. */
    appearance?: 'toolbar' | 'subtle';
}

/**
 * "?" affordance that opens the contextual help for one documentation topic.
 * Mounted in dialog and drawer titles; the app-shell equivalent is
 * HelpMenuButton, which resolves the topic from the current route.
 */
export function HelpButton({ topic, appearance = 'subtle' }: HelpButtonProps) {
    const t = useText();
    const [open, setOpen] = useState(false);

    const trigger =
        appearance === 'toolbar' ? (
            <ToolbarButton icon={<QuestionCircleIcon />} aria-label={t.help.open} onClick={() => setOpen(true)} />
        ) : (
            <Button
                appearance="subtle"
                size="small"
                icon={<QuestionCircleIcon />}
                aria-label={t.help.open}
                onClick={() => setOpen(true)}
            />
        );

    return (
        <>
            <Tooltip content={t.help.open} relationship="label">
                {trigger}
            </Tooltip>
            <HelpDialog slug={topic} open={open} onOpenChange={setOpen} />
        </>
    );
}
