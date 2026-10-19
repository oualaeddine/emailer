import { useCallback, useEffect, useMemo, useState } from 'react';
import { MessageBar, MessageBarBody, Spinner } from '@fluentui/react-components';
import { useText } from '@/Hooks/useText';
import { countSegmentRules, fetchConditionAttributes, searchCampaignOptions, searchListOptions } from '@/Lib/api/segments';
import type { ContactAttribute } from '@/Lib/types/attributes';
import type { ConditionBuilderProps, ConditionOptionSources, FieldCatalog, FieldCategory, RuleTree } from '@/Lib/types/segments';
import { buildFieldCatalog } from './catalog';
import { ConditionBuilder } from './ConditionBuilder';

/**
 * API-wired helpers around the pure `ConditionBuilder` (which never calls the
 * API itself, §45.5.3). Hosts may use them or wire their own `Lib/api/*`.
 */

export interface UseFieldCatalogResult {
    /** Static fields immediately; attribute fields added once GET /contact-attributes resolves. */
    fieldCatalog: FieldCatalog;
    attributes: ContactAttribute[];
    loading: boolean;
    failed: boolean;
    reload: () => void;
}

/** Loads GET /contact-attributes and builds the §45.5.3 field catalog. */
export function useFieldCatalog(categories?: FieldCategory[]): UseFieldCatalogResult {
    const [attributes, setAttributes] = useState<ContactAttribute[]>([]);
    const [loading, setLoading] = useState(true);
    const [failed, setFailed] = useState(false);
    const [version, setVersion] = useState(0);

    useEffect(() => {
        let cancelled = false;
        setLoading(true);
        setFailed(false);
        fetchConditionAttributes()
            .then((rows) => {
                if (!cancelled) setAttributes(rows);
            })
            .catch(() => {
                if (!cancelled) setFailed(true);
            })
            .finally(() => {
                if (!cancelled) setLoading(false);
            });

        return () => {
            cancelled = true;
        };
    }, [version]);

    const categoriesKey = categories?.join(',') ?? '';
    const fieldCatalog = useMemo(
        () => buildFieldCatalog(attributes, categoriesKey ? { categories: categoriesKey.split(',') as FieldCategory[] } : {}),
        [attributes, categoriesKey],
    );
    const reload = useCallback(() => setVersion((v) => v + 1), []);

    return { fieldCatalog, attributes, loading, failed, reload };
}

/** Lists (GET /recipient-lists) and campaigns (GET /campaigns). Users and tags have no lookup endpoint in doc 45 yet. */
export const defaultConditionOptionSources: ConditionOptionSources = {
    lists: searchListOptions,
    campaigns: searchCampaignOptions,
};

/** POST /segments/count → number, for `onCount`. */
export function countRuleTree(tree: RuleTree, signal: AbortSignal): Promise<number> {
    return countSegmentRules(tree, signal).then((result) => result.count);
}

export type ConnectedConditionBuilderProps = Omit<ConditionBuilderProps, 'fieldCatalog' | 'onCount' | 'optionSources'> & {
    /** Provided catalog wins; otherwise fetched from GET /contact-attributes. */
    fieldCatalog?: FieldCatalog;
    /** Provided counter wins; `false` hides the count; default POST /segments/count. */
    onCount?: ConditionBuilderProps['onCount'] | false;
    /** Merged over `defaultConditionOptionSources`. */
    optionSources?: ConditionOptionSources;
};

/**
 * `ConditionBuilder` that fetches its own field catalog and wires the default
 * option sources and live count when the host does not provide them.
 */
export function ConnectedConditionBuilder({ fieldCatalog, onCount, optionSources, categories, ...rest }: ConnectedConditionBuilderProps) {
    const t = useText();
    const fetched = useFieldCatalog(fieldCatalog ? undefined : categories);
    const sources = useMemo(() => ({ ...defaultConditionOptionSources, ...optionSources }), [optionSources]);

    if (!fieldCatalog && fetched.loading && fetched.attributes.length === 0) {
        return <Spinner size="small" label={t.common.loading} />;
    }

    return (
        <>
            {!fieldCatalog && fetched.failed && (
                <MessageBar intent="warning">
                    <MessageBarBody>{t.common.genericError}</MessageBarBody>
                </MessageBar>
            )}
            <ConditionBuilder
                {...rest}
                categories={categories}
                fieldCatalog={fieldCatalog ?? fetched.fieldCatalog}
                optionSources={sources}
                onCount={onCount === false ? undefined : (onCount ?? countRuleTree)}
            />
        </>
    );
}
