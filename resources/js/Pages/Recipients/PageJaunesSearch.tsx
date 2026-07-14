import { FormEvent, useState } from 'react';
import { Head } from '@inertiajs/react';
import {
    Badge,
    Button,
    Card,
    Input,
    Spinner,
    Text,
    Title1,
    Title3,
    makeStyles,
    tokens,
} from '@fluentui/react-components';
import { SearchRegular } from '@fluentui/react-icons';
import { AppShell } from '@/Components/Shell/AppShell';
import { useText } from '@/Hooks/useText';
import { searchPageJaunesCompanies } from '@/Lib/api/pagejaunes';
import type { PageJaunesCompany } from '@/Lib/types/pagejaunes';

const useStyles = makeStyles({
    searchBar: {
        display: 'flex',
        gap: tokens.spacingHorizontalS,
        marginBottom: tokens.spacingVerticalL,
        maxWidth: '480px',
    },
    results: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalM,
    },
    card: {
        padding: tokens.spacingVerticalM,
    },
    badges: {
        display: 'flex',
        gap: tokens.spacingHorizontalXS,
        marginTop: tokens.spacingVerticalXS,
    },
    emails: {
        marginTop: tokens.spacingVerticalS,
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalXXS,
    },
});

/**
 * docs/06-pagejaunes-integration.md §6.7 — Email Import Workflow (search step).
 * The "import selected emails as recipients" action is wired once the
 * Recipients module's create-recipient service exists (docs/34-roadmap.md
 * build order places PageJaunes before Recipients) — this page currently
 * covers search/browse only.
 */
export default function PageJaunesSearch() {
    const styles = useStyles();
    const t = useText();

    const [query, setQuery] = useState('');
    const [results, setResults] = useState<PageJaunesCompany[]>([]);
    const [loading, setLoading] = useState(false);
    const [searched, setSearched] = useState(false);

    async function handleSearch(event: FormEvent) {
        event.preventDefault();
        if (query.trim() === '') {
            return;
        }
        setLoading(true);
        try {
            const companies = await searchPageJaunesCompanies(query);
            setResults(companies);
            setSearched(true);
        } finally {
            setLoading(false);
        }
    }

    return (
        <AppShell>
            <Head title={t.pagejaunes.title} />
            <Title1>{t.pagejaunes.title}</Title1>
            <form className={styles.searchBar} onSubmit={handleSearch}>
                <Input
                    contentBefore={<SearchRegular />}
                    placeholder={t.pagejaunes.searchPlaceholder}
                    value={query}
                    onChange={(_, data) => setQuery(data.value)}
                    style={{ flex: 1 }}
                />
                <Button appearance="primary" type="submit" disabled={loading}>
                    {t.pagejaunes.search}
                </Button>
            </form>

            {loading && <Spinner label={t.common.loading} />}

            {!loading && searched && results.length === 0 && <Text>{t.pagejaunes.noResults}</Text>}
            {!loading && !searched && <Text>{t.pagejaunes.emptyQuery}</Text>}

            <div className={styles.results}>
                {results.map((company) => (
                    <Card key={company.external_id} className={styles.card}>
                        <Title3>{company.company_name ?? company.abv_company_name}</Title3>
                        <Text block>
                            {[company.locality_label, company.wilaya_label].filter(Boolean).join(', ')}
                        </Text>
                        {company.address && <Text block>{company.address}</Text>}
                        <div className={styles.badges}>
                            {company.is_distributor && <Badge appearance="tint">Distributeur</Badge>}
                            {company.is_exporter && <Badge appearance="tint">Exportateur</Badge>}
                            {company.is_importer && <Badge appearance="tint">Importateur</Badge>}
                            {company.is_producer && <Badge appearance="tint">Producteur</Badge>}
                        </div>
                        <div className={styles.emails}>
                            {company.emails.length === 0 && (
                                <Badge color="danger" appearance="tint">
                                    {t.pagejaunes.noEmail}
                                </Badge>
                            )}
                            {company.emails.map((email) => (
                                <Text key={email.external_id}>{email.email}</Text>
                            ))}
                        </div>
                    </Card>
                ))}
            </div>
        </AppShell>
    );
}
