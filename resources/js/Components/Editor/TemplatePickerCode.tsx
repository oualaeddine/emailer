import { ChangeEvent, useRef, useState } from 'react';
import { Button, Field, Input, MessageBar, MessageBarBody, Spinner, Textarea, makeStyles, tokens } from '@fluentui/react-components';
import { useText } from '@/Hooks/useText';
import { importTemplateFromUrl, importTemplateZip } from '@/Lib/api/templates';
import type { HtmlImportResult } from '@/Lib/types/templates';

const useStyles = makeStyles({
    root: { display: 'flex', flexDirection: 'column', gap: tokens.spacingVerticalL },
    row: { display: 'flex', gap: tokens.spacingHorizontalS, alignItems: 'flex-end' },
    grow: { flexGrow: 1 },
});

interface TemplatePickerCodeProps {
    disabled?: boolean;
    /** Called with the final HTML (pasted or imported) when the user confirms. */
    onUse: (html: string) => void;
}

/** "Coder" tab: paste HTML / import from a URL / upload a ZIP (docs/43 §43.4.3). */
export function TemplatePickerCode({ disabled, onUse }: TemplatePickerCodeProps) {
    const styles = useStyles();
    const t = useText();
    const fileRef = useRef<HTMLInputElement>(null);
    const [html, setHtml] = useState('');
    const [url, setUrl] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [warnings, setWarnings] = useState<string[]>([]);

    async function runImport(task: () => Promise<HtmlImportResult>) {
        setBusy(true);
        setError(null);
        setWarnings([]);
        try {
            const result = await task();
            setHtml(result.html);
            setWarnings(result.warnings);
        } catch (err) {
            const message = (err as { response?: { data?: { message?: unknown; errors?: Record<string, string[]> } } })?.response?.data;
            const first = message?.errors ? Object.values(message.errors)[0]?.[0] : undefined;
            setError(first ?? (typeof message?.message === 'string' ? message.message : t.templatePicker.code.invalidUrl));
        } finally {
            setBusy(false);
        }
    }

    function handleZip(event: ChangeEvent<HTMLInputElement>) {
        const file = event.target.files?.[0];
        event.target.value = '';
        if (file) {
            void runImport(() => importTemplateZip(file));
        }
    }

    const c = t.templatePicker.code;

    return (
        <div className={styles.root}>
            <Field label={c.importUrl}>
                <div className={styles.row}>
                    <Input
                        className={styles.grow}
                        type="url"
                        value={url}
                        placeholder={c.urlPlaceholder}
                        onChange={(_, data) => setUrl(data.value)}
                    />
                    <Button disabled={busy || disabled || url.trim() === ''} onClick={() => void runImport(() => importTemplateFromUrl({ url: url.trim() }))}>
                        {c.import}
                    </Button>
                </div>
            </Field>
            <Field label={c.importZip} hint={c.zipHelp}>
                <div>
                    <input ref={fileRef} type="file" accept=".zip,application/zip" hidden onChange={handleZip} />
                    <Button disabled={busy || disabled} onClick={() => fileRef.current?.click()}>
                        {t.common.upload}
                    </Button>
                </div>
            </Field>
            {busy && <Spinner size="tiny" label={c.importing} />}
            {error && (
                <MessageBar intent="error">
                    <MessageBarBody>{error}</MessageBarBody>
                </MessageBar>
            )}
            {warnings.length > 0 && (
                <MessageBar intent="warning">
                    <MessageBarBody>
                        {c.warnings} : {warnings.join(' ')}
                    </MessageBarBody>
                </MessageBar>
            )}
            <Field label={c.paste}>
                <Textarea
                    rows={12}
                    resize="vertical"
                    value={html}
                    placeholder={c.pastePlaceholder}
                    onChange={(_, data) => setHtml(data.value)}
                />
            </Field>
            <div>
                <Button appearance="primary" disabled={disabled || busy || html.trim() === ''} onClick={() => onUse(html)}>
                    {t.templatePicker.use}
                </Button>
            </div>
        </div>
    );
}
