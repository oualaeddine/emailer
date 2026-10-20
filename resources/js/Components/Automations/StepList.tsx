import { createContext, useContext, type ComponentType } from 'react';
import {
    Badge,
    Button,
    Card,
    Menu,
    MenuItem,
    MenuList,
    MenuPopover,
    MenuTrigger,
    Text,
    Tooltip,
    makeStyles,
    mergeClasses,
    tokens,
} from '@fluentui/react-components';
import {
    AddRegular,
    BranchForkRegular,
    ClockRegular,
    DeleteRegular,
    EditRegular,
    ErrorCircleRegular,
    FlagRegular,
    MailRegular,
    MoreHorizontalRegular,
    PersonAddRegular,
    PersonDeleteRegular,
    PersonEditRegular,
    WarningRegular,
} from '@fluentui/react-icons';
import { countConditions } from '@/Components/Contacts/ConditionBuilder';
import { formatText } from '@/Components/Campaigns/campaignUi';
import { useText } from '@/Hooks/useText';
import type { AutomationStepType } from '@/Lib/types/automations';
import { formatAttributeValue } from './AttributeValueEditor';
import {
    MAX_CONDITION_DEPTH,
    MAX_STEPS,
    STEP_TYPES,
    listTerminates,
    widthUnits,
    type BranchRef,
    type DraftStep,
    type StepIssue,
} from './graph';
import { useAutomationLookups } from './lookups';

/** Width of one layout column (a step card plus breathing room), in px. */
export const COLUMN_WIDTH = 300;
const CONNECTOR_HEIGHT = 56;

export const STEP_ICONS: Record<AutomationStepType, ComponentType> = {
    send_email: MailRegular,
    wait: ClockRegular,
    condition: BranchForkRegular,
    add_to_list: PersonAddRegular,
    remove_from_list: PersonDeleteRegular,
    update_attribute: PersonEditRegular,
    end: FlagRegular,
};

export interface FlowContextValue {
    readOnly: boolean;
    selectedKey: string | null;
    totalSteps: number;
    issues: Map<string, StepIssue>;
    serverErrors: Record<string, Record<string, string>>;
    onOpenStep: (key: string) => void;
    onRemoveStep: (key: string) => void;
    onInsert: (ref: BranchRef, index: number, type: AutomationStepType) => void;
}

export const FlowContext = createContext<FlowContextValue | null>(null);

function useFlow(): FlowContextValue {
    const value = useContext(FlowContext);
    if (!value) throw new Error('StepList must be rendered inside FlowCanvas.');

    return value;
}

const useStyles = makeStyles({
    list: {
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        width: '100%',
    },
    connector: {
        position: 'relative',
        display: 'flex',
        justifyContent: 'center',
        width: '100%',
        height: `${CONNECTOR_HEIGHT}px`,
        flexShrink: 0,
    },
    line: {
        fill: 'none',
        stroke: tokens.colorNeutralStroke1,
        strokeWidth: '2px',
    },
    insert: {
        position: 'absolute',
        top: '50%',
        left: '50%',
        transform: 'translate(-50%, -60%)',
        backgroundColor: tokens.colorNeutralBackground1,
    },
    card: {
        width: `${COLUMN_WIDTH - 40}px`,
        boxSizing: 'border-box',
        flexShrink: 0,
    },
    cardSelected: {
        boxShadow: `0 0 0 ${tokens.strokeWidthThick} ${tokens.colorBrandStroke1}`,
    },
    cardInvalid: {
        outline: `${tokens.strokeWidthThick} solid ${tokens.colorPaletteRedBorder2}`,
    },
    cardWarning: {
        outline: `${tokens.strokeWidthThin} dashed ${tokens.colorPaletteMarigoldBorder2}`,
    },
    cardHeader: {
        display: 'flex',
        alignItems: 'center',
        gap: tokens.spacingHorizontalS,
        minWidth: 0,
    },
    cardTitle: {
        flexGrow: 1,
        minWidth: 0,
    },
    icon: {
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        width: '32px',
        height: '32px',
        flexShrink: 0,
        borderRadius: tokens.borderRadiusMedium,
        backgroundColor: tokens.colorNeutralBackground3,
        color: tokens.colorNeutralForeground2,
        fontSize: '18px',
    },
    iconCondition: {
        backgroundColor: tokens.colorPaletteMarigoldBackground1,
        color: tokens.colorPaletteMarigoldForeground2,
    },
    iconEnd: {
        backgroundColor: tokens.colorNeutralBackground5,
    },
    summary: {
        color: tokens.colorNeutralForeground2,
        overflowWrap: 'anywhere',
    },
    stats: {
        color: tokens.colorNeutralForeground3,
    },
    problem: {
        display: 'flex',
        alignItems: 'flex-start',
        gap: tokens.spacingHorizontalXS,
        color: tokens.colorPaletteRedForeground1,
    },
    warning: {
        display: 'flex',
        alignItems: 'center',
        gap: tokens.spacingHorizontalXS,
        color: tokens.colorPaletteMarigoldForeground2,
    },
    fork: {
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'stretch',
        flexShrink: 0,
    },
    forkSvg: {
        display: 'block',
        width: '100%',
        height: '20px',
        overflow: 'visible',
    },
    branches: {
        display: 'grid',
        alignItems: 'stretch',
    },
    branch: {
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        minWidth: 0,
    },
    branchLabel: {
        flexShrink: 0,
    },
    spacer: {
        flexGrow: 1,
        minHeight: '12px',
        width: '2px',
    },
    spacerLine: {
        backgroundColor: tokens.colorNeutralStroke1,
    },
    unreachable: {
        maxWidth: `${COLUMN_WIDTH - 40}px`,
        textAlign: 'center',
        color: tokens.colorPaletteMarigoldForeground2,
        paddingTop: tokens.spacingVerticalS,
        paddingBottom: tokens.spacingVerticalS,
    },
});

/** Vertical SVG connector with an optional "+" insert menu. */
function Connector({ branch, index, listLength, depth }: { branch: BranchRef; index: number; listLength: number; depth: number }) {
    const styles = useStyles();
    const t = useText();
    const flow = useFlow();
    const full = flow.totalSteps >= MAX_STEPS;

    return (
        <div className={styles.connector}>
            <svg width="24" height={CONNECTOR_HEIGHT} viewBox={`0 0 24 ${CONNECTOR_HEIGHT}`} aria-hidden="true" focusable="false">
                <path className={styles.line} d={`M12 0 V${CONNECTOR_HEIGHT - 2}`} />
                <path className={styles.line} d={`M7 ${CONNECTOR_HEIGHT - 8} L12 ${CONNECTOR_HEIGHT - 2} L17 ${CONNECTOR_HEIGHT - 8}`} />
            </svg>
            {!flow.readOnly && (
                <Menu positioning="after">
                    <MenuTrigger disableButtonEnhancement>
                        <Tooltip content={full ? formatText(t.automations.editor.step.maxSteps, MAX_STEPS) : t.automations.addStep} relationship="label">
                            <Button
                                className={styles.insert}
                                size="small"
                                shape="circular"
                                appearance="outline"
                                icon={<AddRegular />}
                                disabled={full}
                                aria-label={t.automations.editor.step.insertHere}
                            />
                        </Tooltip>
                    </MenuTrigger>
                    <MenuPopover>
                        <MenuList>
                            {STEP_TYPES.map((type) => {
                                const Icon = STEP_ICONS[type];
                                const tooDeep = type === 'condition' && depth >= MAX_CONDITION_DEPTH;
                                const notLast = type === 'end' && index !== listLength;
                                const hint = tooDeep ? formatText(t.automations.editor.step.maxDepth, MAX_CONDITION_DEPTH) : notLast ? t.automations.editor.step.endOnlyLast : undefined;

                                return (
                                    <MenuItem
                                        key={type}
                                        icon={<Icon />}
                                        disabled={tooDeep || notLast}
                                        secondaryContent={hint ? <WarningRegular aria-label={hint} /> : undefined}
                                        title={hint}
                                        onClick={() => flow.onInsert(branch, index, type)}
                                    >
                                        {t.automations.steps[type]}
                                    </MenuItem>
                                );
                            })}
                        </MenuList>
                    </MenuPopover>
                </Menu>
            )}
        </div>
    );
}

function useStepSummary(): (step: DraftStep) => string {
    const t = useText();
    const { attributes, label } = useAutomationLookups();

    return (step) => {
        switch (step.type) {
            case 'send_email': {
                if (!step.config.template_id) return t.automations.editor.step.noTemplate;
                const name = label('templates', step.config.template_id) ?? `${step.config.template_id.slice(0, 8)}…`;

                return step.config.subject ? `${name} · « ${step.config.subject} »` : name;
            }
            case 'wait':
                return formatText(t.automations.editor.step.waitSummary, step.config.amount, t.automations.waitUnits[step.config.unit]);
            case 'condition': {
                const count = countConditions(step.config.rules);

                return count === 0 ? t.automations.editor.step.noCondition : formatText(t.automations.editor.step.conditionCount, count);
            }
            case 'add_to_list':
            case 'remove_from_list':
                return step.config.list_id ? (label('lists', step.config.list_id) ?? `${step.config.list_id.slice(0, 8)}…`) : t.automations.editor.step.noList;
            case 'update_attribute': {
                if (!step.config.attribute) return t.automations.editor.step.noAttribute;
                const definition = attributes.find((a) => a.name === step.config.attribute);

                return formatText(
                    t.automations.editor.step.attributeSummary,
                    definition?.label ?? step.config.attribute,
                    formatAttributeValue(definition, step.config.value, t.conditionBuilder.booleanValues.true, t.conditionBuilder.booleanValues.false),
                );
            }
            case 'end':
                return t.automations.editor.step.endSummary;
        }
    };
}

function StepCard({ step }: { step: DraftStep }) {
    const styles = useStyles();
    const t = useText();
    const flow = useFlow();
    const summary = useStepSummary();
    const Icon = STEP_ICONS[step.type];
    const serverErrors = flow.serverErrors[step.key];
    const serverMessage = serverErrors ? Object.values(serverErrors)[0] : undefined;
    const issue = flow.issues.get(step.key);
    const title = t.automations.steps[step.type];

    return (
        <Card
            size="small"
            className={mergeClasses(
                styles.card,
                flow.selectedKey === step.key && styles.cardSelected,
                serverMessage ? styles.cardInvalid : issue ? styles.cardWarning : undefined,
            )}
            onClick={() => flow.onOpenStep(step.key)}
            aria-label={`${title} : ${summary(step)}`}
        >
            <div className={styles.cardHeader}>
                <span
                    className={mergeClasses(styles.icon, step.type === 'condition' && styles.iconCondition, step.type === 'end' && styles.iconEnd)}
                    aria-hidden="true"
                >
                    <Icon />
                </span>
                <Text weight="semibold" className={styles.cardTitle} truncate wrap={false}>
                    {title}
                </Text>
                {!flow.readOnly && (
                    <Menu>
                        <MenuTrigger disableButtonEnhancement>
                            <Button
                                size="small"
                                appearance="subtle"
                                icon={<MoreHorizontalRegular />}
                                aria-label={`${t.common.moreActions} — ${title}`}
                                onClick={(event) => event.stopPropagation()}
                            />
                        </MenuTrigger>
                        <MenuPopover onClick={(event) => event.stopPropagation()}>
                            <MenuList>
                                <MenuItem icon={<EditRegular />} onClick={() => flow.onOpenStep(step.key)}>
                                    {t.automations.editor.step.edit}
                                </MenuItem>
                                <MenuItem icon={<DeleteRegular />} onClick={() => flow.onRemoveStep(step.key)}>
                                    {t.automations.removeStep}
                                </MenuItem>
                            </MenuList>
                        </MenuPopover>
                    </Menu>
                )}
            </div>
            <Text size={300} className={styles.summary}>
                {summary(step)}
            </Text>
            {step.stats && (
                <Text size={200} className={styles.stats}>
                    {formatText(t.automations.editor.step.stats, step.stats.entered, step.stats.completed)}
                </Text>
            )}
            {serverMessage ? (
                <Text size={200} className={styles.problem} role="alert">
                    <ErrorCircleRegular aria-hidden="true" />
                    {serverMessage}
                </Text>
            ) : issue ? (
                <Text size={200} className={styles.warning}>
                    <WarningRegular aria-hidden="true" />
                    {issue === 'wait_range' ? t.automations.editor.step.waitRange : t.automations.editor.step.incomplete}
                </Text>
            ) : null}
        </Card>
    );
}

/** Condition fork: split into Oui / Non columns, rejoin below the branches that do not end. */
function Fork({ step, depth }: { step: DraftStep; depth: number }) {
    const styles = useStyles();
    const t = useText();
    const unit = Math.max(widthUnits(step.yes), widthUnits(step.no)) * COLUMN_WIDTH;
    const yesOpen = !listTerminates(step.yes);
    const noOpen = !listTerminates(step.no);

    let join = '';
    if (yesOpen && noOpen) join = 'M25 0 V10 M75 0 V10 M25 10 H75 M50 10 V20';
    else if (yesOpen) join = 'M25 0 V10 H50 V20';
    else if (noOpen) join = 'M75 0 V10 H50 V20';

    const branch = (key: 'yes' | 'no', open: boolean) => (
        <div className={styles.branch}>
            <Badge className={styles.branchLabel} appearance="tint" color={key === 'yes' ? 'success' : 'danger'} size="medium">
                {key === 'yes' ? t.automations.yesBranch : t.automations.noBranch}
            </Badge>
            <StepList steps={step[key]} branch={{ parentKey: step.key, branch: key }} depth={depth + 1} />
            <div className={mergeClasses(styles.spacer, open && styles.spacerLine)} aria-hidden="true" />
        </div>
    );

    return (
        <div className={styles.fork} style={{ width: `${unit * 2}px` }}>
            <svg className={styles.forkSvg} viewBox="0 0 100 20" preserveAspectRatio="none" aria-hidden="true" focusable="false">
                <path className={styles.line} vectorEffect="non-scaling-stroke" d="M50 0 V10 M25 10 H75 M25 10 V20 M75 10 V20" />
            </svg>
            <div className={styles.branches} style={{ gridTemplateColumns: `${unit}px ${unit}px` }}>
                {branch('yes', yesOpen)}
                {branch('no', noOpen)}
            </div>
            {join && (
                <svg className={styles.forkSvg} viewBox="0 0 100 20" preserveAspectRatio="none" aria-hidden="true" focusable="false">
                    <path className={styles.line} vectorEffect="non-scaling-stroke" d={join} />
                </svg>
            )}
        </div>
    );
}

export interface StepListProps {
    steps: DraftStep[];
    branch: BranchRef;
    /** Number of condition steps above this list. */
    depth: number;
}

/** Vertical linear list of steps with "+" insert points between them. */
export function StepList({ steps, branch, depth }: StepListProps) {
    const t = useText();
    const styles = useStyles();
    const items = [];
    let reachable = true;

    for (let index = 0; index < steps.length; index += 1) {
        const step = steps[index];
        if (!reachable) {
            items.push(
                <Text key={`unreachable-${step.key}`} size={200} className={styles.unreachable} role="note">
                    <WarningRegular aria-hidden="true" /> {t.automations.editor.step.unreachable}
                </Text>,
            );
            reachable = true;
        }
        items.push(<StepCard key={step.key} step={step} />);
        if (step.type === 'condition') {
            items.push(<Fork key={`fork-${step.key}`} step={step} depth={depth} />);
        }
        const terminates = step.type === 'end' || (step.type === 'condition' && listTerminates(step.yes) && listTerminates(step.no));
        if (!terminates) {
            items.push(<Connector key={`after-${step.key}`} branch={branch} index={index + 1} listLength={steps.length} depth={depth} />);
        } else {
            reachable = false;
        }
    }

    return (
        <div className={styles.list}>
            <Connector branch={branch} index={0} listLength={steps.length} depth={depth} />
            {items}
        </div>
    );
}

