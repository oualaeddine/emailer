import { useMemo, useState } from 'react';
import {
    Combobox,
    Dropdown,
    Field,
    Input,
    Option,
    Radio,
    RadioGroup,
    Switch,
    Tag,
    TagGroup,
    Text,
    Textarea,
    makeStyles,
    tokens,
} from '@fluentui/react-components';
import { formText } from '@/Components/Forms/strings';
import { areValidEmails, isHttpUrl } from '@/Components/Forms/model';
import { useText } from '@/Hooks/useText';
import type { Uuid } from '@/Lib/types/common';
import type { FormSettings } from '@/Lib/types/forms';
import type { ContactList } from '@/Lib/types/lists';
import type { TemplateV2 } from '@/Lib/types/templates';

const useStyles = makeStyles({
    root: { display: 'flex', flexDirection: 'column', gap: tokens.spacingVerticalL },
    section: { display: 'flex', flexDirection: 'column', gap: tokens.spacingVerticalS },
    nested: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalS,
        paddingLeft: tokens.spacingHorizontalL,
        borderLeft: `${tokens.strokeWidthThick} solid ${tokens.colorNeutralStroke2}`,
    },
    muted: { color: tokens.colorNeutralForeground3 },
});

const DEFAULT_TEMPLATE = '__default';

type AfterSubmit = 'message' | 'redirect';

interface Props {
    settings: FormSettings;
    listIds: Uuid[];
    /** Lists from GET /recipient-lists. */
    lists: ContactList[];
    /** Names of lists already attached to the form (`SubscriptionForm.lists`), in case they are not in `lists`. */
    knownListNames: Record<Uuid, string>;
    templates: TemplateV2[];
    onSettingsChange: (settings: FormSettings) => void;
    onListIdsChange: (ids: Uuid[]) => void;
    errors?: Record<string, string>;
}

/** *Paramètres* tab — target lists, double opt-in, after-submit behaviour, captcha, notifications. */
export function SettingsPanel({ settings, listIds, lists, knownListNames, templates, onSettingsChange, onListIdsChange, errors = {} }: Props) {
    const styles = useStyles();
    const t = useText();
    const [query, setQuery] = useState('');
    const [afterSubmit, setAfterSubmit] = useState<AfterSubmit>(settings.redirect_url !== null ? 'redirect' : 'message');
    const [notifyText, setNotifyText] = useState(settings.notify_emails.join('\n'));
    const set = <K extends keyof FormSettings>(key: K, value: FormSettings[K]) => onSettingsChange({ ...settings, [key]: value });

    const names = useMemo(() => {
        const map: Record<Uuid, string> = { ...knownListNames };
        for (const list of lists) map[list.id] = list.name;

        return map;
    }, [lists, knownListNames]);
    const filtered = lists.filter((list) => list.name.toLowerCase().includes(query.trim().toLowerCase()));
    const templateName = templates.find((tpl) => tpl.id === settings.doi_template_id)?.name;

    const errorFor = (key: string, local?: string | false) => {
        const message = errors[key] ?? (local || undefined);

        return message ? { validationMessage: message, validationState: 'error' as const } : {};
    };

    return (
        <div className={styles.root}>
            <div className={styles.section}>
                <Field label={t.forms.settings.targetLists} required {...errorFor('list_ids')}>
                    <Combobox
                        multiselect
                        freeform
                        placeholder={formText.settings.listsPlaceholder}
                        value={query}
                        selectedOptions={listIds}
                        onChange={(event) => setQuery(event.target.value)}
                        onOptionSelect={(_, d) => {
                            onListIdsChange(d.selectedOptions);
                            setQuery('');
                        }}
                    >
                        {filtered.length === 0 ? (
                            <Option key="__none" value="__none" disabled text="">
                                {t.common.noResults}
                            </Option>
                        ) : (
                            filtered.map((list) => (
                                <Option key={list.id} value={list.id} text={list.name}>
                                    {`${list.name} (${list.contact_count.toLocaleString('fr-FR')})`}
                                </Option>
                            ))
                        )}
                    </Combobox>
                </Field>
                {listIds.length > 0 && (
                    <TagGroup
                        aria-label={t.forms.settings.targetLists}
                        onDismiss={(_, d) => onListIdsChange(listIds.filter((id) => id !== d.value))}
                    >
                        {listIds.map((id) => (
                            <Tag key={id} value={id} dismissible dismissIcon={{ 'aria-label': t.common.remove }}>
                                {names[id] ?? id}
                            </Tag>
                        ))}
                    </TagGroup>
                )}
            </div>

            <div className={styles.section}>
                <Switch
                    label={t.forms.settings.doubleOptIn}
                    checked={settings.double_opt_in}
                    onChange={(_, d) => set('double_opt_in', d.checked)}
                />
                <Text size={200} className={styles.muted}>
                    {t.forms.settings.doubleOptInHelp}
                </Text>
                {settings.double_opt_in && (
                    <div className={styles.nested}>
                        <Field label={t.forms.settings.doiTemplate} {...errorFor('settings.doi_template_id')}>
                            <Dropdown
                                value={templateName ?? t.common.default}
                                selectedOptions={[settings.doi_template_id ?? DEFAULT_TEMPLATE]}
                                onOptionSelect={(_, d) =>
                                    set('doi_template_id', !d.optionValue || d.optionValue === DEFAULT_TEMPLATE ? null : d.optionValue)
                                }
                            >
                                <Option value={DEFAULT_TEMPLATE}>{t.common.default}</Option>
                                {templates.map((tpl) => (
                                    <Option key={tpl.id} value={tpl.id}>
                                        {tpl.name}
                                    </Option>
                                ))}
                            </Dropdown>
                        </Field>
                        <Field
                            label={`${t.forms.settings.doiRedirect} (${t.common.optional})`}
                            {...errorFor(
                                'settings.doi_redirect_url',
                                !isHttpUrl(settings.doi_redirect_url) && formText.settings.invalidUrl,
                            )}
                        >
                            <Input
                                type="url"
                                placeholder="https://"
                                value={settings.doi_redirect_url ?? ''}
                                onChange={(_, d) => set('doi_redirect_url', d.value === '' ? null : d.value)}
                            />
                        </Field>
                    </div>
                )}
            </div>

            <div className={styles.section}>
                <Field label={formText.settings.afterSubmit}>
                    <RadioGroup
                        value={afterSubmit}
                        onChange={(_, d) => {
                            const mode = d.value as AfterSubmit;
                            setAfterSubmit(mode);
                            set('redirect_url', mode === 'redirect' ? (settings.redirect_url ?? '') : null);
                        }}
                    >
                        <Radio value="message" label={formText.settings.showMessage} />
                        <Radio value="redirect" label={formText.settings.redirect} />
                    </RadioGroup>
                </Field>
                <div className={styles.nested}>
                    {afterSubmit === 'message' ? (
                        <Field label={t.forms.settings.successMessage} required {...errorFor('settings.success_message')}>
                            <Textarea
                                value={settings.success_message}
                                resize="vertical"
                                maxLength={1000}
                                onChange={(_, d) => set('success_message', d.value)}
                            />
                        </Field>
                    ) : (
                        <Field
                            label={t.forms.settings.redirectUrl}
                            required
                            {...errorFor('settings.redirect_url', !isHttpUrl(settings.redirect_url) && formText.settings.invalidUrl)}
                        >
                            <Input
                                type="url"
                                placeholder="https://"
                                value={settings.redirect_url ?? ''}
                                onChange={(_, d) => set('redirect_url', d.value)}
                            />
                        </Field>
                    )}
                </div>
            </div>

            <div className={styles.section}>
                <Switch
                    label={t.forms.settings.captcha}
                    checked={settings.captcha_enabled}
                    onChange={(_, d) => set('captcha_enabled', d.checked)}
                />
                <Switch
                    label={t.forms.settings.updateExisting}
                    checked={settings.update_existing}
                    onChange={(_, d) => set('update_existing', d.checked)}
                />
            </div>

            <Field
                label={`${t.forms.settings.notifyEmails} (${t.common.optional})`}
                hint={formText.settings.notifyEmailsHelp}
                {...errorFor('settings.notify_emails', !areValidEmails(settings.notify_emails) && formText.settings.invalidEmails)}
            >
                <Textarea
                    value={notifyText}
                    resize="vertical"
                    placeholder="equipe@exemple.dz"
                    onChange={(_, d) => {
                        setNotifyText(d.value);
                        set(
                            'notify_emails',
                            d.value
                                .split(/[\n,;]/)
                                .map((v) => v.trim())
                                .filter((v) => v !== ''),
                        );
                    }}
                />
            </Field>
        </div>
    );
}
