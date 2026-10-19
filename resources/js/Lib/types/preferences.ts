/**
 * docs/45-brevo-api-contracts.md §45.6.6 (WP-74 unsubscribe pages,
 * update-profile forms, public preferences page) and §45.6.7 (tracking
 * settings keys).
 */
import type { IsoDateTime, PageDesign, Uuid } from '@/Lib/types/common';

export interface UnsubscribePageContent {
    title: string;
    body: string;
    button_label: string;
    confirmation_title: string;
    confirmation_message: string;
    show_reason_survey: boolean;
    reasons: string[];
    show_preferences_link: boolean;
    resubscribe_enabled: boolean;
}

export interface UnsubscribePage {
    id: Uuid;
    name: string;
    is_default: boolean;
    content: UnsubscribePageContent;
    design: PageDesign;
    created_at: IsoDateTime;
    updated_at: IsoDateTime;
}

export interface UnsubscribePageInput {
    name: string;
    content: UnsubscribePageContent;
    design: PageDesign;
}

export interface UpdateProfileField {
    attribute: string;
    label: string;
    required: boolean;
}

export interface UpdateProfileFormContent {
    title: string;
    intro: string;
    submit_label: string;
    success_message: string;
}

export interface UpdateProfileForm {
    id: Uuid;
    name: string;
    is_default: boolean;
    fields: UpdateProfileField[];
    /** Lists the contact may opt in/out individually. */
    list_ids: Uuid[];
    content: UpdateProfileFormContent;
    design: PageDesign;
    created_at: IsoDateTime;
    updated_at: IsoDateTime;
}

export interface UpdateProfileFormInput {
    name: string;
    fields: UpdateProfileField[];
    list_ids: Uuid[];
    content: UpdateProfileFormContent;
    design: PageDesign;
}

/** Keys stored through GET/PATCH /settings (§45.6.7). */
export type TrackingSettingKey =
    | 'tracking.opens_enabled'
    | 'tracking.clicks_enabled'
    | 'tracking.domain'
    | 'tracking.utm_default_enabled'
    | 'tracking.utm_default_source'
    | 'tracking.utm_default_medium'
    | 'tracking.geoip_enabled'
    | 'campaigns.unengaged_warning_percent'
    | 'senders.spf_include'
    | 'senders.dmarc_rua'
    | 'forms.default_doi_template_id';

export interface TrackingSettings {
    'tracking.opens_enabled': boolean;
    'tracking.clicks_enabled': boolean;
    'tracking.domain': string | null;
    'tracking.utm_default_enabled': boolean;
    'tracking.utm_default_source': string;
    'tracking.utm_default_medium': string;
    'tracking.geoip_enabled': boolean;
}
