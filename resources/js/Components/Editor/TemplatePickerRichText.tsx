import { useState } from 'react';
import { Button, Text, makeStyles, tokens } from '@fluentui/react-components';
import { RichTextEditor } from '@/Components/Composer/RichTextEditor';
import { useText } from '@/Hooks/useText';

const useStyles = makeStyles({
    root: { display: 'flex', flexDirection: 'column', gap: tokens.spacingVerticalM },
});

interface TemplatePickerRichTextProps {
    disabled?: boolean;
    onUse: (html: string) => void;
}

/** "Éditeur de texte enrichi" tab: reuses the Composer's RichTextEditor. */
export function TemplatePickerRichText({ disabled, onUse }: TemplatePickerRichTextProps) {
    const styles = useStyles();
    const t = useText();
    const [html, setHtml] = useState('');

    return (
        <div className={styles.root}>
            <Text>{t.templatePicker.richTextHelp}</Text>
            <RichTextEditor value={html} onChange={setHtml} />
            <div>
                <Button
                    appearance="primary"
                    disabled={disabled || html.replace(/<[^>]*>/g, '').trim() === ''}
                    onClick={() => onUse(`<html><body>${html}</body></html>`)}
                >
                    {t.templatePicker.use}
                </Button>
            </div>
        </div>
    );
}
