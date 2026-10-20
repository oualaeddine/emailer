import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { Combobox, Option, Spinner, Tag, TagGroup, makeStyles, tokens } from '@fluentui/react-components';
import { defaultConditionOptionSources, useFieldCatalog } from '@/Components/Contacts/ConditionBuilder';
import { useText } from '@/Hooks/useText';
import {
    searchAutomationCampaigns,
    searchAutomationForms,
    searchAutomationLists,
    searchAutomationTemplates,
    type LookupOption,
} from '@/Lib/api/automations';
import type { ContactAttribute } from '@/Lib/types/attributes';
import type { Uuid } from '@/Lib/types/common';
import type { ConditionOptionSources, FieldCatalog, FieldCategory } from '@/Lib/types/segments';

/**
 * Shared lookups of the automations editor: contact attributes + field
 * catalog (ConditionBuilder), and searchable lists / forms / transactional
 * templates / sent campaigns with a label cache so trigger and step cards can
 * show names for the uuids stored in their config.
 */

export type LookupKind = 'lists' | 'forms' | 'templates' | 'campaigns';

/** Automations hide the PagesJaunes category (§45.5.3). */
export const AUTOMATION_CATEGORIES: FieldCategory[] = ['attribute', 'contact', 'list', 'engagement', 'date'];

const SOURCES: Record<LookupKind, (q: string) => Promise<LookupOption[]>> = {
    lists: searchAutomationLists,
    forms: searchAutomationForms,
    templates: searchAutomationTemplates,
    campaigns: searchAutomationCampaigns,
};

interface AutomationLookups {
    attributes: ContactAttribute[];
    fieldCatalog: FieldCatalog;
    catalogLoading: boolean;
    optionSources: ConditionOptionSources;
    search: (kind: LookupKind, q: string) => Promise<LookupOption[]>;
    label: (kind: LookupKind, id: Uuid | null | undefined) => string | null;
    hint: (kind: LookupKind, id: Uuid | null | undefined) => string | null;
}

const LookupsContext = createContext<AutomationLookups | null>(null);

export function AutomationLookupsProvider({ children }: { children: ReactNode }) {
    const { fieldCatalog, attributes, loading } = useFieldCatalog(AUTOMATION_CATEGORIES);
    const cache = useRef<Record<LookupKind, Map<string, LookupOption>>>({
        lists: new Map(),
        forms: new Map(),
        templates: new Map(),
        campaigns: new Map(),
    });
    const [, setVersion] = useState(0);

    const search = useCallback(async (kind: LookupKind, q: string) => {
        const options = await SOURCES[kind](q);
        let added = false;
        for (const option of options) {
            if (!cache.current[kind].has(option.value)) added = true;
            cache.current[kind].set(option.value, option);
        }
        if (added) setVersion((v) => v + 1);

        return options;
    }, []);

    // Warm the label cache (first page of each source) so saved uuids show names.
    useEffect(() => {
        (Object.keys(SOURCES) as LookupKind[]).forEach((kind) => {
            search(kind, '').catch(() => undefined);
        });
    }, [search]);

    const label = useCallback((kind: LookupKind, id: Uuid | null | undefined) => (id ? (cache.current[kind].get(id)?.label ?? null) : null), []);
    const hint = useCallback((kind: LookupKind, id: Uuid | null | undefined) => (id ? (cache.current[kind].get(id)?.hint ?? null) : null), []);

    const value: AutomationLookups = {
        attributes,
        fieldCatalog,
        catalogLoading: loading,
        optionSources: defaultConditionOptionSources,
        search,
        label,
        hint,
    };

    return <LookupsContext.Provider value={value}>{children}</LookupsContext.Provider>;
}

export function useAutomationLookups(): AutomationLookups {
    const value = useContext(LookupsContext);
    if (!value) {
        throw new Error('useAutomationLookups must be used inside <AutomationLookupsProvider>.');
    }

    return value;
}

/** "Nom" or a shortened uuid when the label is not cached (yet). */
export function useLabelOf(kind: LookupKind): (id: Uuid) => string {
    const { label } = useAutomationLookups();

    return (id: Uuid) => label(kind, id) ?? `${id.slice(0, 8)}…`;
}

const useStyles = makeStyles({
    stack: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalXS,
        minWidth: 0,
    },
    input: {
        width: '100%',
        minWidth: 0,
    },
});

export interface EntityPickerProps {
    kind: LookupKind;
    values: Uuid[];
    onChange: (values: Uuid[]) => void;
    /** Single selection (the new pick replaces the previous one). */
    single?: boolean;
    disabled?: boolean;
    invalid?: boolean;
    ariaLabel: string;
}

/** Searchable picker over one lookup source; selected items as dismissible tags. */
export function EntityPicker({ kind, values, onChange, single = false, disabled, invalid, ariaLabel }: EntityPickerProps) {
    const styles = useStyles();
    const t = useText();
    const { search } = useAutomationLookups();
    const labelOf = useLabelOf(kind);
    const [query, setQuery] = useState('');
    const [options, setOptions] = useState<LookupOption[]>([]);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        let cancelled = false;
        const timer = window.setTimeout(() => {
            setLoading(true);
            search(kind, query)
                .then((result) => {
                    if (!cancelled) setOptions(result);
                })
                .catch(() => {
                    if (!cancelled) setOptions([]);
                })
                .finally(() => {
                    if (!cancelled) setLoading(false);
                });
        }, 250);

        return () => {
            cancelled = true;
            window.clearTimeout(timer);
        };
    }, [kind, query, search]);

    const selectedOptions = useMemo(() => values.map(String), [values]);

    return (
        <div className={styles.stack}>
            <Combobox
                className={styles.input}
                multiselect={!single}
                freeform
                disabled={disabled}
                aria-label={ariaLabel}
                aria-invalid={invalid || undefined}
                placeholder={t.common.searchPlaceholder}
                value={query}
                selectedOptions={selectedOptions}
                onChange={(event) => setQuery(event.target.value)}
                onOptionSelect={(_, data) => {
                    if (single) {
                        onChange(data.optionValue ? [data.optionValue] : []);
                    } else {
                        onChange(data.selectedOptions);
                    }
                    setQuery('');
                }}
            >
                {loading && options.length === 0 ? (
                    <Option value="__loading" text={t.common.loading} disabled>
                        <Spinner size="extra-tiny" label={t.common.loading} />
                    </Option>
                ) : options.length === 0 ? (
                    <Option value="__empty" text={t.common.noResults} disabled>
                        {t.common.noResults}
                    </Option>
                ) : (
                    options.map((option) => (
                        <Option key={option.value} value={option.value} text={option.label}>
                            {option.label}
                        </Option>
                    ))
                )}
            </Combobox>
            {values.length > 0 && (
                <TagGroup
                    size="small"
                    disabled={disabled}
                    aria-label={ariaLabel}
                    onDismiss={(_, data) => onChange(values.filter((v) => v !== String(data.value)))}
                    style={{ flexWrap: 'wrap', gap: tokens.spacingHorizontalXS }}
                >
                    {values.map((id) => (
                        <Tag key={id} value={id} dismissible dismissIcon={{ 'aria-label': t.common.remove }}>
                            {labelOf(id)}
                        </Tag>
                    ))}
                </TagGroup>
            )}
        </div>
    );
}
