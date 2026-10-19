/**
 * docs/45-brevo-api-contracts.md §45.7.4 — WP-90 forms backend, WP-90U
 * builder (`Marketing/Forms`, `Marketing/FormEdit`). /api/v1/forms,
 * public web:/f/{form}.
 */
import type { ContactAttributeOption, ContactAttributeType } from '@/Lib/types/attributes';
import type { IsoDateTime, PageDesign, UserRef, Uuid } from '@/Lib/types/common';

/** Labels: fr.forms.statusLabels. */
export type SubscriptionFormStatus = 'draft' | 'active' | 'inactive';

/** Labels: fr.forms.submissionStatus. */
export type FormSubmissionStatus = 'pending_confirmation' | 'confirmed' | 'subscribed' | 'rejected' | 'spam';

/** Builder tabs. Labels: fr.forms.tabs. */
export type FormBuilderTab = 'fields' | 'design' | 'settings' | 'share';

export type FormLayout = 'vertical' | 'horizontal';

export interface FormField {
    attribute: string;
    label: string;
    placeholder: string | null;
    required: boolean;
    help_text: string | null;
    /** Derived from the attribute (read-only in responses). */
    type: ContactAttributeType;
    options?: ContactAttributeOption[];
}

export type FormFieldInput = Omit<FormField, 'type' | 'options'>;

export interface FormGdpr {
    enabled: boolean;
    text: string;
    required: boolean;
}

export interface FormDesign extends PageDesign {
    button_text: string;
    title: string;
    description: string;
    layout: FormLayout;
}

export interface FormSettings {
    double_opt_in: boolean;
    doi_template_id: Uuid | null;
    doi_redirect_url: string | null;
    success_message: string;
    redirect_url: string | null;
    captcha_enabled: boolean;
    update_existing: boolean;
    notify_emails: string[];
}

export interface FormShare {
    hosted_url: string;
    iframe_code: string;
    embed_html: string;
}

export interface SubscriptionForm {
    id: Uuid;
    name: string;
    status: SubscriptionFormStatus;
    list_ids: Uuid[];
    lists: { id: Uuid; name: string }[];
    fields: FormField[];
    gdpr: FormGdpr;
    design: FormDesign;
    settings: FormSettings;
    share: FormShare;
    submissions_count: number;
    confirmed_count: number;
    last_submission_at: IsoDateTime | null;
    created_by: UserRef | null;
    created_at: IsoDateTime;
    updated_at: IsoDateTime;
}

export interface SubscriptionFormInput {
    name: string;
    list_ids: Uuid[];
    fields: FormFieldInput[];
    gdpr: FormGdpr;
    design: FormDesign;
    settings: FormSettings;
}

export interface FormListQuery {
    q?: string;
    status?: SubscriptionFormStatus;
    page?: number;
    per_page?: number;
}

export interface FormSubmission {
    id: Uuid;
    email: string;
    recipient_id: Uuid | null;
    status: FormSubmissionStatus;
    data: Record<string, unknown>;
    submitted_at: IsoDateTime;
    confirmed_at: IsoDateTime | null;
    ip_country: string | null;
}

export interface FormSubmissionsQuery {
    status?: FormSubmissionStatus;
    cursor?: string;
    per_page?: number;
}

/** POST web:/f/{form} with Accept: application/json. */
export interface FormSubmitResult {
    status: Extract<FormSubmissionStatus, 'subscribed' | 'pending_confirmation'>;
    message: string;
    redirect_url: string | null;
}
