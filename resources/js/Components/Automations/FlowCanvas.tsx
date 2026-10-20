import { useLayoutEffect, useRef } from 'react';
import { useText } from '@/Hooks/useText';
import { Text, makeStyles, tokens } from '@fluentui/react-components';
import { FlagRegular } from '@fluentui/react-icons';
import type { AutomationStepType, AutomationTrigger } from '@/Lib/types/automations';
import { countSteps, listTerminates, widthUnits, type BranchRef, type DraftStep, type StepIssue } from './graph';
import { COLUMN_WIDTH, FlowContext, StepList, type FlowContextValue } from './StepList';
import { TriggerCard } from './TriggerCard';

const useStyles = makeStyles({
    canvas: {
        overflow: 'auto',
        maxWidth: '100%',
        boxSizing: 'border-box',
        borderRadius: tokens.borderRadiusMedium,
        backgroundColor: tokens.colorNeutralBackground2,
        backgroundImage: `radial-gradient(${tokens.colorNeutralStroke2} 1px, transparent 1px)`,
        backgroundSize: '16px 16px',
        padding: `${tokens.spacingVerticalXL} ${tokens.spacingHorizontalM}`,
        minHeight: '420px',
    },
    column: {
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        margin: '0 auto',
    },
    endPill: {
        display: 'inline-flex',
        alignItems: 'center',
        gap: tokens.spacingHorizontalXS,
        padding: `${tokens.spacingVerticalXS} ${tokens.spacingHorizontalM}`,
        borderRadius: tokens.borderRadiusCircular,
        backgroundColor: tokens.colorNeutralBackground5,
        color: tokens.colorNeutralForeground2,
    },
});

export interface FlowCanvasProps {
    trigger: AutomationTrigger | null;
    triggerInvalid: boolean;
    steps: DraftStep[];
    /** `"trigger"`, a step key, or null. */
    selectedKey: string | null;
    readOnly: boolean;
    issues: Map<string, StepIssue>;
    serverErrors: Record<string, Record<string, string>>;
    onOpenTrigger: () => void;
    onOpenStep: (key: string) => void;
    onRemoveStep: (key: string) => void;
    onInsert: (ref: BranchRef, index: number, type: AutomationStepType) => void;
    ariaLabel: string;
}

/**
 * docs/43 §43.4.19 — Brevo-style linear workflow: trigger card on top, steps
 * stacked vertically with SVG connectors, condition steps forking into Oui /
 * Non columns. Pure layout (no graph library): every column is
 * `COLUMN_WIDTH` wide and a condition needs twice its widest branch, so
 * branch centres sit exactly at 25 % / 75 % of the fork.
 */
export function FlowCanvas({
    trigger,
    triggerInvalid,
    steps,
    selectedKey,
    readOnly,
    issues,
    serverErrors,
    onOpenTrigger,
    onOpenStep,
    onRemoveStep,
    onInsert,
    ariaLabel,
}: FlowCanvasProps) {
    const styles = useStyles();
    const t = useText();
    const canvasRef = useRef<HTMLDivElement>(null);
    const width = widthUnits(steps) * COLUMN_WIDTH;

    // Wider than the viewport (nested conditions, phones): centre on the trunk.
    useLayoutEffect(() => {
        const canvas = canvasRef.current;
        if (canvas && canvas.scrollWidth > canvas.clientWidth) {
            canvas.scrollLeft = (canvas.scrollWidth - canvas.clientWidth) / 2;
        }
    }, [width]);

    const context: FlowContextValue = {
        readOnly,
        selectedKey,
        totalSteps: countSteps(steps),
        issues,
        serverErrors,
        onOpenStep,
        onRemoveStep,
        onInsert,
    };

    return (
        <FlowContext.Provider value={context}>
            <div ref={canvasRef} className={styles.canvas} role="region" aria-label={ariaLabel}>
                <div className={styles.column} style={{ width: `${width}px` }}>
                    <TriggerCard trigger={trigger} selected={selectedKey === 'trigger'} invalid={triggerInvalid} onOpen={onOpenTrigger} />
                    <StepList steps={steps} branch={{ parentKey: null }} depth={0} />
                    {!listTerminates(steps) && (
                        <Text size={200} className={styles.endPill}>
                            <FlagRegular aria-hidden="true" />
                            {t.automations.editor.step.flowEnd}
                        </Text>
                    )}
                </div>
            </div>
        </FlowContext.Provider>
    );
}
