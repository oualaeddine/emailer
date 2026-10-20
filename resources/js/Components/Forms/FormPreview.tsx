import { useMemo, useState } from 'react';
import { Text, ToggleButton, makeStyles, tokens } from '@fluentui/react-components';
import { DesktopRegular, PhoneRegular } from '@fluentui/react-icons';
import { EmailFrame } from '@/Components/Campaigns/EmailFrame';
import type { FormDraft } from '@/Components/Forms/model';
import { renderFormHtml } from '@/Components/Forms/renderFormHtml';
import { useText } from '@/Hooks/useText';

const useStyles = makeStyles({
    root: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalS,
        minWidth: 0,
    },
    header: {
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        gap: tokens.spacingHorizontalS,
    },
    toggles: { display: 'flex', gap: tokens.spacingHorizontalXS },
    stage: {
        display: 'flex',
        justifyContent: 'center',
        borderRadius: tokens.borderRadiusMedium,
        border: `${tokens.strokeWidthThin} solid ${tokens.colorNeutralStroke2}`,
        backgroundColor: tokens.colorNeutralBackground3,
        overflow: 'hidden',
    },
});

type Device = 'desktop' | 'mobile';

interface Props {
    draft: FormDraft;
    height?: number;
}

/** Live preview of the hosted form, rebuilt client-side from the unsaved draft. */
export function FormPreview({ draft, height = 640 }: Props) {
    const styles = useStyles();
    const t = useText();
    const [device, setDevice] = useState<Device>('desktop');
    const html = useMemo(
        () => renderFormHtml(draft, { captcha: t.forms.editor.preview.captcha, selectPlaceholder: t.forms.editor.preview.selectPlaceholder }),
        [draft],
    );

    return (
        <section className={styles.root} aria-label={t.forms.preview}>
            <div className={styles.header}>
                <Text weight="semibold">{t.forms.preview}</Text>
                <div className={styles.toggles}>
                    <ToggleButton
                        size="small"
                        appearance="subtle"
                        icon={<DesktopRegular />}
                        checked={device === 'desktop'}
                        aria-label={t.common.desktop}
                        title={t.common.desktop}
                        onClick={() => setDevice('desktop')}
                    />
                    <ToggleButton
                        size="small"
                        appearance="subtle"
                        icon={<PhoneRegular />}
                        checked={device === 'mobile'}
                        aria-label={t.common.mobile}
                        title={t.common.mobile}
                        onClick={() => setDevice('mobile')}
                    />
                </div>
            </div>
            <div className={styles.stage}>
                <EmailFrame html={html} title={t.forms.preview} width={device === 'mobile' ? 375 : '100%'} height={height} />
            </div>
        </section>
    );
}
