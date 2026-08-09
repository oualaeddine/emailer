import { TabList, Tab, makeStyles, tokens } from '@fluentui/react-components';
import { docsLocaleNames, docsLocales, type DocsLocale } from '@/Lib/docs/ui';

const useStyles = makeStyles({
    root: {
        // Keep the toggle itself left-to-right so "Français | العربية" always
        // reads in the same order regardless of the documentation direction.
        direction: 'ltr',
    },
    tab: {
        fontSize: tokens.fontSizeBase200,
    },
});

interface DocsLocaleToggleProps {
    locale: DocsLocale;
    onChange: (locale: DocsLocale) => void;
    label: string;
}

/** FR/AR switch for the documentation surfaces (the app interface stays French). */
export function DocsLocaleToggle({ locale, onChange, label }: DocsLocaleToggleProps) {
    const styles = useStyles();

    return (
        <TabList
            className={styles.root}
            size="small"
            appearance="subtle"
            selectedValue={locale}
            aria-label={label}
            onTabSelect={(_, data) => onChange(data.value as DocsLocale)}
        >
            {docsLocales.map((value) => (
                <Tab key={value} className={styles.tab} value={value}>
                    {docsLocaleNames[value]}
                </Tab>
            ))}
        </TabList>
    );
}
