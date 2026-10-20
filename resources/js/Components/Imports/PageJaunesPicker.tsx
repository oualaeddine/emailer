import { FormEvent, useState } from 'react';
import { Badge, Button, Checkbox, Input, Spinner, Text, makeStyles, tokens } from '@fluentui/react-components';
import { SearchRegular } from '@fluentui/react-icons';
import { useText } from '@/Hooks/useText';
import { createImportFromPageJaunes } from '@/Lib/api/imports';
import { searchPageJaunesCompanies } from '@/Lib/api/pagejaunes';
import type { ImportJobV2 } from '@/Lib/types/imports';
import type { PageJaunesCompany } from '@/Lib/types/pagejaunes';
import { missingLabels } from '@/Components/Imports/labels';

const useStyles = makeStyles({
    bar: { display: 'flex', gap: tokens.spacingHorizontalS },
    row: { display: 'flex', flexDirection: 'column', padding: tokens.spacingVerticalXS },
    list: { display: 'flex', flexDirection: 'column', maxHeight: '320px', overflowY: 'auto' },
});

interface Props {
    busy: boolean;
    onSubmit: (action: () => Promise<ImportJobV2>) => void;
}

/**
 * PagesJaunes tab of the Fichier step. Uses the same search API as
 * Recipients/PageJaunesSearch (a full page, so its logic is reused rather than embedded).
 */
export function PageJaunesPicker({ busy, onSubmit }: Props) {
    const styles = useStyles();
    const t = useText();
    const [query, setQuery] = useState('');
    const [loading, setLoading] = useState(false);
    const [searched, setSearched] = useState(false);
    const [results, setResults] = useState<PageJaunesCompany[]>([]);
    const [selected, setSelected] = useState<Record<number, PageJaunesCompany>>({});

    async function search(event: FormEvent) {
        event.preventDefault();
        if (query.trim() === '') {
            return;
        }
        setLoading(true);
        try {
            setResults(await searchPageJaunesCompanies(query));
            setSearched(true);
        } finally {
            setLoading(false);
        }
    }

    const companies = Object.values(selected).filter((c) => c.emails.length > 0);
    const contacts = companies.flatMap((c) =>
        c.emails.map((e) => ({ email: e.email, company_name: c.company_name ?? c.abv_company_name, external_id: c.external_id })),
    );

    return (
        <>
            <Text>{t.imports.wizard.pagejaunesLabel}</Text>
            <form className={styles.bar} onSubmit={search}>
                <Input
                    style={{ flex: 1 }}
                    contentBefore={<SearchRegular />}
                    placeholder={t.pagejaunes.searchPlaceholder}
                    value={query}
                    onChange={(_, d) => setQuery(d.value)}
                />
                <Button type="submit" disabled={loading}>
                    {t.pagejaunes.search}
                </Button>
            </form>
            {loading && <Spinner label={t.common.loading} />}
            {!loading && searched && results.length === 0 && <Text>{t.pagejaunes.noResults}</Text>}
            <div className={styles.list}>
                {results.map((company) => (
                    <div key={company.external_id} className={styles.row}>
                        <Checkbox
                            checked={selected[company.external_id] !== undefined}
                            disabled={company.emails.length === 0}
                            label={company.company_name ?? company.abv_company_name ?? company.code}
                            onChange={(_, d) =>
                                setSelected((s) => {
                                    const next = { ...s };
                                    if (d.checked) {
                                        next[company.external_id] = company;
                                    } else {
                                        delete next[company.external_id];
                                    }
                                    return next;
                                })
                            }
                        />
                        <Text size={200}>
                            {company.emails.length === 0 ? (
                                <Badge color="danger" appearance="tint">
                                    {t.pagejaunes.noEmail}
                                </Badge>
                            ) : (
                                company.emails.map((e) => e.email).join(', ')
                            )}
                        </Text>
                    </div>
                ))}
            </div>
            <div>
                <Button
                    appearance="primary"
                    disabled={busy || contacts.length === 0}
                    onClick={() =>
                        onSubmit(() =>
                            createImportFromPageJaunes(
                                companies.map((c) => c.external_id),
                                contacts,
                            ),
                        )
                    }
                >
                    {t.common.continue} ({contacts.length} {missingLabels.contactsCount})
                </Button>
            </div>
        </>
    );
}
