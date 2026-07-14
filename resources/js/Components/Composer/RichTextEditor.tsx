import { useEffect, useRef } from 'react';
import { Toolbar, ToolbarButton, ToolbarDivider, makeStyles, tokens } from '@fluentui/react-components';
import {
    TextBoldRegular,
    TextItalicRegular,
    TextUnderlineRegular,
    TextBulletListRegular,
    TextNumberListLtrRegular,
    LinkRegular,
} from '@fluentui/react-icons';

const useStyles = makeStyles({
    root: {
        border: `${tokens.strokeWidthThin} solid ${tokens.colorNeutralStroke1}`,
        borderRadius: tokens.borderRadiusMedium,
    },
    toolbar: {
        borderBottomWidth: tokens.strokeWidthThin,
        borderBottomStyle: 'solid',
        borderBottomColor: tokens.colorNeutralStroke2,
    },
    editable: {
        minHeight: '320px',
        padding: tokens.spacingVerticalM,
        outline: 'none',
        fontFamily: tokens.fontFamilyBase,
        fontSize: tokens.fontSizeBase300,
        overflowY: 'auto',
    },
});

interface RichTextEditorProps {
    value: string;
    onChange: (html: string) => void;
}

/**
 * docs/11-email-composer.md §11.2 — Composer Modes: "Rich text" surface.
 * A functional contentEditable-based WYSIWYG (bold/italic/underline/
 * lists/link via the browser's native editing commands) rather than a
 * heavyweight third-party editor dependency not called for by the docs.
 */
export function RichTextEditor({ value, onChange }: RichTextEditorProps) {
    const styles = useStyles();
    const editableRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (editableRef.current && editableRef.current.innerHTML !== value) {
            editableRef.current.innerHTML = value;
        }
    }, [value]);

    function exec(command: string, arg?: string) {
        document.execCommand(command, false, arg);
        editableRef.current?.focus();
        handleInput();
    }

    function handleInput() {
        if (editableRef.current) {
            onChange(editableRef.current.innerHTML);
        }
    }

    function insertLink() {
        const url = window.prompt('URL du lien :');
        if (url) {
            exec('createLink', url);
        }
    }

    return (
        <div className={styles.root}>
            <Toolbar className={styles.toolbar}>
                <ToolbarButton icon={<TextBoldRegular />} onClick={() => exec('bold')} aria-label="Gras" />
                <ToolbarButton icon={<TextItalicRegular />} onClick={() => exec('italic')} aria-label="Italique" />
                <ToolbarButton
                    icon={<TextUnderlineRegular />}
                    onClick={() => exec('underline')}
                    aria-label="Souligné"
                />
                <ToolbarDivider />
                <ToolbarButton
                    icon={<TextBulletListRegular />}
                    onClick={() => exec('insertUnorderedList')}
                    aria-label="Liste à puces"
                />
                <ToolbarButton
                    icon={<TextNumberListLtrRegular />}
                    onClick={() => exec('insertOrderedList')}
                    aria-label="Liste numérotée"
                />
                <ToolbarDivider />
                <ToolbarButton icon={<LinkRegular />} onClick={insertLink} aria-label="Insérer un lien" />
            </Toolbar>
            <div
                ref={editableRef}
                className={styles.editable}
                contentEditable
                onInput={handleInput}
                onBlur={handleInput}
                suppressContentEditableWarning
            />
        </div>
    );
}
