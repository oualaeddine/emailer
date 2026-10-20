import type { ContactAttribute } from '@/Lib/types/attributes';
import type { FormDesign, FormField, FormSettings, SubscriptionForm, SubscriptionFormInput } from '@/Lib/types/forms';
import type { TranslationDictionary } from '@/Lib/i18n/fr';
import { formText } from '@/Components/Forms/strings';

/** The attribute every form must collect (publish answers 422 without it). */
export const EMAIL_ATTRIBUTE = 'EMAIL';

/** Brevo's form builder has no "user" field type. */
export const PICKABLE = (attribute: ContactAttribute) => attribute.type !== 'user';

export const FONT_FAMILIES = [
    'Segoe UI, sans-serif',
    'Arial, Helvetica, sans-serif',
    'Helvetica, Arial, sans-serif',
    'Verdana, Geneva, sans-serif',
    'Tahoma, Geneva, sans-serif',
    'Trebuchet MS, sans-serif',
    'Georgia, serif',
    'Times New Roman, Times, serif',
    'Courier New, Courier, monospace',
] as const;

export function defaultDesign(t: TranslationDictionary, title: string): FormDesign {
    return {
        logo_url: null,
        primary_color: '#0F6CBD',
        background_color: '#F5F5F5',
        text_color: '#242424',
        font_family: 'Segoe UI, sans-serif',
        button_text_color: '#FFFFFF',
        border_radius: 4,
        button_text: t.forms.public.submit,
        title,
        description: '',
        layout: 'vertical',
    };
}

export function defaultSettings(t: TranslationDictionary): FormSettings {
    return {
        double_opt_in: true,
        doi_template_id: null,
        doi_redirect_url: null,
        success_message: t.forms.public.thankYou,
        redirect_url: null,
        captcha_enabled: true,
        update_existing: true,
        notify_emails: [],
    };
}

export function emailField(): FormField {
    return {
        attribute: EMAIL_ATTRIBUTE,
        label: formText.fields.emailLabel,
        placeholder: null,
        required: true,
        help_text: null,
        type: 'text',
    };
}

/** POST /forms body for a brand-new form (name dialog). */
export function newFormInput(t: TranslationDictionary, name: string): SubscriptionFormInput {
    return {
        name,
        list_ids: [],
        fields: [toFieldInput(emailField())],
        gdpr: { enabled: true, text: formText.fields.defaultGdprText, required: true },
        design: defaultDesign(t, name),
        settings: defaultSettings(t),
    };
}

export function fieldFromAttribute(attribute: ContactAttribute): FormField {
    return {
        attribute: attribute.name,
        label: attribute.label || attribute.name,
        placeholder: null,
        required: attribute.name === EMAIL_ATTRIBUTE,
        help_text: null,
        type: attribute.type,
        ...(attribute.type === 'category' || attribute.type === 'multiple_choice' ? { options: attribute.options } : {}),
    };
}

export function toFieldInput(field: FormField): SubscriptionFormInput['fields'][number] {
    return {
        attribute: field.attribute,
        label: field.label,
        placeholder: field.placeholder,
        required: field.required,
        help_text: field.help_text,
    };
}

/** Editable draft kept by the builder (full fields so the preview knows types/options). */
export interface FormDraft {
    name: string;
    list_ids: string[];
    fields: FormField[];
    gdpr: SubscriptionForm['gdpr'];
    design: FormDesign;
    settings: FormSettings;
}

export function draftFromForm(form: SubscriptionForm): FormDraft {
    return {
        name: form.name,
        list_ids: form.list_ids,
        fields: form.fields,
        gdpr: form.gdpr,
        design: form.design,
        settings: form.settings,
    };
}

export function draftToInput(draft: FormDraft): SubscriptionFormInput {
    return {
        name: draft.name.trim(),
        list_ids: draft.list_ids,
        fields: draft.fields.map(toFieldInput),
        gdpr: draft.gdpr,
        design: draft.design,
        settings: {
            ...draft.settings,
            doi_redirect_url: blankToNull(draft.settings.doi_redirect_url),
            redirect_url: blankToNull(draft.settings.redirect_url),
        },
    };
}

function blankToNull(value: string | null): string | null {
    return value === null || value.trim() === '' ? null : value.trim();
}

export function isHttpUrl(value: string | null): boolean {
    if (value === null || value.trim() === '') return true;
    try {
        const url = new URL(value.trim());

        return url.protocol === 'http:' || url.protocol === 'https:';
    } catch {
        return false;
    }
}

const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

export function areValidEmails(values: string[]): boolean {
    return values.every((value) => EMAIL_RE.test(value));
}
