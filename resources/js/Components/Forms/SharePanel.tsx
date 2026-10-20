import { useEffect, useRef, useState } from 'react';
import { Button, Field, Input, MessageBar, MessageBarBody, Textarea, makeStyles, tokens } from '@fluentui/react-components';
import { CheckmarkRegular, CopyRegular, OpenRegular } from '@fluentui/react-icons';
import { formText } from '@/Components/Forms/strings';
import { useText } from '@/Hooks/useText';
import type { FormShare, SubscriptionFormStatus } from '@/Lib/types/forms';

const useStyles = makeStyles({
    root: { display: 'flex', flexDirection: 'column', gap: tokens.spacingVerticalL },
    row: { display: 'flex', gap: tokens.spacingHorizontalS, alignItems: 'center', flexWrap: 'wrap' },
    grow: { flex: '1 1 260px', minWidth: 0 },
    code: { fontFamily: tokens.fontFamilyMonospace, fontSize: tokens.fontSizeBase200 },
    copyRow: { display: 'flex', justifyContent: 'flex-end', marginTop: tokens.spacingVerticalXS },
});

type CopyKey = 'hosted_url' | 'iframe_code' | 'embed_html';

async function copyText(value: string): Promise<boolean> {
    try {
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(value);

            return true;
        }
    } catch {
        // fall through to the legacy path
    }
    const area = document.createElement('textarea');
    area.value = value;
    area.setAttribute('readonly', '');
    area.style.position = 'fixed';
    area.style.opacity = '0';
    document.body.appendChild(area);
    area.select();
    let ok = false;
    try {
        ok = document.execCommand('copy');
    } catch {
        ok = false;
    }
    document.body.removeChild(area);

    return ok;
}

interface Props {
    share: FormShare;
    status: SubscriptionFormStatus;
}

/** *Partager* tab — hosted "Quick share" URL `/f/{uuid}`, iframe and HTML embed snippets. */
export function SharePanel({ share, status }: Props) {
    const styles = useStyles();
    const t = useText();
    const [copied, setCopied] = useState<CopyKey | null>(null);
    const timer = useRef<number | null>(null);

    useEffect(
        () => () => {
            if (timer.current !== null) window.clearTimeout(timer.current);
        },
        [],
    );

    async function copy(key: CopyKey) {
        if (await copyText(share[key])) {
            setCopied(key);
            if (timer.current !== null) window.clearTimeout(timer.current);
            timer.current = window.setTimeout(() => setCopied(null), 2000);
        }
    }

    const copyButton = (key: CopyKey) => (
        <Button
            appearance={copied === key ? 'primary' : 'secondary'}
            icon={copied === key ? <CheckmarkRegular /> : <CopyRegular />}
            onClick={() => void copy(key)}
        >
            {copied === key ? t.common.copied : t.forms.share.copy}
        </Button>
    );

    return (
        <div className={styles.root}>
            {status !== 'active' && (
                <MessageBar intent="warning">
                    <MessageBarBody>{formText.share.inactive}</MessageBarBody>
                </MessageBar>
            )}

            <Field label={t.forms.share.hostedUrl}>
                <div className={styles.row}>
                    <Input className={styles.grow} readOnly value={share.hosted_url} onFocus={(e) => e.target.select()} />
                    {copyButton('hosted_url')}
                    <Button
                        appearance="subtle"
                        icon={<OpenRegular />}
                        as="a"
                        href={share.hosted_url}
                        target="_blank"
                        rel="noreferrer"
                    >
                        {formText.share.open}
                    </Button>
                </div>
            </Field>

            <Field label={t.forms.share.iframe}>
                <Textarea
                    className={styles.code}
                    readOnly
                    resize="vertical"
                    rows={4}
                    value={share.iframe_code}
                    onFocus={(e) => e.target.select()}
                />
            </Field>
            <div className={styles.copyRow}>{copyButton('iframe_code')}</div>

            <Field label={t.forms.share.embed}>
                <Textarea
                    className={styles.code}
                    readOnly
                    resize="vertical"
                    rows={4}
                    value={share.embed_html}
                    onFocus={(e) => e.target.select()}
                />
            </Field>
            <div className={styles.copyRow}>{copyButton('embed_html')}</div>
        </div>
    );
}
