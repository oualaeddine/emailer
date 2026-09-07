import { useState, useEffect, type ReactNode } from 'react';
import {
    Button,
    Card,
    Subtitle2,
    Body1,
    Caption1,
    Title1,
    makeStyles,
    tokens,
} from '@fluentui/react-components';
import {
    BookInformation24Regular,
    ChevronDownRegular,
    ChevronUpRegular,
    InfoRegular,
    QuestionCircleRegular,
} from '@fluentui/react-icons';
import { pageGuides, type PageGuideStep } from '@/Lib/docs/pageGuides';
import { HelpDialog } from '@/Components/Help/HelpDialog';
import { useText } from '@/Hooks/useText';

const useStyles = makeStyles({
    root: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalM,
        marginBottom: tokens.spacingVerticalL,
    },
    headerRow: {
        display: 'flex',
        flexWrap: 'wrap',
        alignItems: 'center',
        justifyContent: 'space-between',
        gap: tokens.spacingHorizontalM,
    },
    titleGroup: {
        display: 'flex',
        alignItems: 'center',
        gap: tokens.spacingHorizontalM,
    },
    actionsGroup: {
        display: 'flex',
        alignItems: 'center',
        flexWrap: 'wrap',
        gap: tokens.spacingHorizontalS,
    },
    guideCard: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalM,
        padding: tokens.spacingVerticalM,
        backgroundColor: tokens.colorNeutralBackground1,
        borderLeftWidth: tokens.strokeWidthThick,
        borderLeftStyle: 'solid',
        borderLeftColor: tokens.colorBrandStroke1,
        boxShadow: tokens.shadow2,
    },
    purposeBlock: {
        display: 'flex',
        alignItems: 'flex-start',
        gap: tokens.spacingHorizontalS,
    },
    purposeIcon: {
        color: tokens.colorBrandForeground1,
        flexShrink: 0,
        marginTop: '2px',
        fontSize: '20px',
    },
    purposeText: {
        color: tokens.colorNeutralForeground1,
        lineHeight: tokens.lineHeightBase400,
    },
    howToHeader: {
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        paddingTop: tokens.spacingVerticalXS,
        borderTopWidth: tokens.strokeWidthThin,
        borderTopStyle: 'solid',
        borderTopColor: tokens.colorNeutralStroke2,
    },
    stepsGrid: {
        display: 'grid',
        gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))',
        gap: tokens.spacingHorizontalM,
        marginTop: tokens.spacingVerticalXS,
    },
    stepCard: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalXXS,
        padding: tokens.spacingVerticalS,
        backgroundColor: tokens.colorNeutralBackground2,
        borderRadius: tokens.borderRadiusMedium,
        borderLeftWidth: tokens.strokeWidthThick,
        borderLeftStyle: 'solid',
        borderLeftColor: tokens.colorBrandStroke1,
    },
    stepNumber: {
        display: 'inline-flex',
        alignItems: 'center',
        justifyContent: 'center',
        width: '22px',
        height: '22px',
        borderRadius: '50%',
        backgroundColor: tokens.colorBrandBackground,
        color: tokens.colorNeutralForegroundOnBrand,
        fontWeight: tokens.fontWeightBold,
        fontSize: tokens.fontSizeBase200,
        marginBottom: tokens.spacingVerticalXXS,
    },
    stepTitle: {
        fontWeight: tokens.fontWeightSemibold,
        color: tokens.colorNeutralForeground1,
    },
    stepDesc: {
        color: tokens.colorNeutralForeground3,
        lineHeight: tokens.lineHeightBase200,
    },
    footerActions: {
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        flexWrap: 'wrap',
        gap: tokens.spacingHorizontalS,
        paddingTop: tokens.spacingVerticalXS,
    },
});

export interface PageHelpProps {
    topic: string;
    title?: string;
    customPurpose?: string;
    customSteps?: PageGuideStep[];
    actions?: ReactNode;
    children?: ReactNode;
    hideHeaderRow?: boolean;
}

export function PageHelp({
    topic,
    title,
    customPurpose,
    customSteps,
    actions,
    children,
    hideHeaderRow = false,
}: PageHelpProps) {
    const styles = useStyles();
    const t = useText();
    const guide = pageGuides[topic];

    const displayTitle = title ?? guide?.title ?? '';
    const purpose = customPurpose ?? guide?.purpose ?? '';
    const steps = customSteps ?? guide?.howToUse ?? [];

    const storageKey = `pj_page_help_collapsed_${topic}`;
    const [isStepsOpen, setIsStepsOpen] = useState(false);
    const [isDocOpen, setIsDocOpen] = useState(false);

    useEffect(() => {
        const saved = localStorage.getItem(storageKey);
        if (saved !== null) {
            setIsStepsOpen(saved === 'false');
        }
    }, [storageKey]);

    function toggleSteps() {
        setIsStepsOpen((prev) => {
            const next = !prev;
            localStorage.setItem(storageKey, (!next).toString());
            return next;
        });
    }

    return (
        <div className={styles.root}>
            {/* Page Header Row */}
            {!hideHeaderRow && (
                <div className={styles.headerRow}>
                    <div className={styles.titleGroup}>
                        <Title1>{displayTitle}</Title1>
                    </div>
                    <div className={styles.actionsGroup}>
                        {actions}
                        <Button
                            appearance="subtle"
                            icon={<QuestionCircleRegular />}
                            onClick={() => setIsDocOpen(true)}
                        >
                            {t.help.open}
                        </Button>
                    </div>
                </div>
            )}

            {/* Explanatory Help Card */}
            {purpose && (
                <Card className={styles.guideCard}>
                    {/* What is this page used for */}
                    <div className={styles.purposeBlock}>
                        <InfoRegular className={styles.purposeIcon} />
                        <div>
                            <Subtitle2 block style={{ marginBottom: '4px' }}>
                                À quoi sert cette page ?
                            </Subtitle2>
                            <Body1 className={styles.purposeText}>{purpose}</Body1>
                        </div>
                    </div>

                    {/* How to use the page */}
                    {steps.length > 0 && (
                        <>
                            <div className={styles.howToHeader}>
                                <Button
                                    appearance="transparent"
                                    size="small"
                                    icon={isStepsOpen ? <ChevronUpRegular /> : <ChevronDownRegular />}
                                    onClick={toggleSteps}
                                    style={{ padding: 0 }}
                                >
                                    <Subtitle2>
                                        Comment l'utiliser ? ({steps.length} étapes clés)
                                    </Subtitle2>
                                </Button>
                                <Button
                                    appearance="transparent"
                                    size="small"
                                    icon={<BookInformation24Regular />}
                                    onClick={() => setIsDocOpen(true)}
                                >
                                    Documentation complète
                                </Button>
                            </div>

                            {isStepsOpen && (
                                <div className={styles.stepsGrid}>
                                    {steps.map((item) => (
                                        <div key={item.step} className={styles.stepCard}>
                                            <span className={styles.stepNumber}>{item.step}</span>
                                            <Caption1 className={styles.stepTitle}>{item.title}</Caption1>
                                            <Caption1 className={styles.stepDesc}>{item.description}</Caption1>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </>
                    )}
                </Card>
            )}

            {children}

            {/* Complete In-App Documentation Modal */}
            <HelpDialog slug={topic} open={isDocOpen} onOpenChange={setIsDocOpen} />
        </div>
    );
}
