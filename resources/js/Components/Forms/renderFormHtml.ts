import type { FormField } from '@/Lib/types/forms';
import type { FormDraft } from '@/Components/Forms/model';
import { EMAIL_ATTRIBUTE } from '@/Components/Forms/model';

/**
 * Builds the srcdoc of the live preview, mirroring the hosted page
 * `web:/f/{uuid}` (§45.7.4): logo, title, description, one control per field
 * (typed from the attribute), the GDPR checkbox, the captcha slot and the
 * submit button. Rendered in a sandboxed iframe (no scripts, no forms), and
 * every user-supplied value is escaped here.
 */

export interface PreviewLabels {
    captcha: string;
    selectPlaceholder: string;
}

const HEX_RE = /^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i;

function esc(value: string | null | undefined): string {
    return (value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function color(value: string, fallback: string): string {
    return HEX_RE.test(value.trim()) ? value.trim() : fallback;
}

function font(value: string): string {
    const cleaned = value.replace(/[^a-zA-Z0-9 ,'"-]/g, '').trim();

    return cleaned === '' ? 'sans-serif' : cleaned;
}

function safeUrl(value: string | null): string | null {
    if (!value) return null;
    try {
        const url = new URL(value, 'https://example.invalid');

        return url.protocol === 'http:' || url.protocol === 'https:' ? value : null;
    } catch {
        return null;
    }
}

function control(field: FormField, index: number, labels: PreviewLabels): string {
    const id = `f${index}`;
    const placeholder = field.placeholder ? ` placeholder="${esc(field.placeholder)}"` : '';
    const required = field.required ? ' required' : '';
    const name = `attributes[${esc(field.attribute)}]`;
    const options = field.options ?? [];

    switch (field.type) {
        case 'boolean':
            return `<label class="check"><input type="checkbox" name="${name}"${required}> <span>${esc(field.label)}${field.required ? ' <b class="req">*</b>' : ''}</span></label>`;
        case 'category':
            return `<select id="${id}" name="${name}"${required}><option value="">${esc(field.placeholder ?? labels.selectPlaceholder)}</option>${options
                .map((o) => `<option value="${esc(o.value)}">${esc(o.label)}</option>`)
                .join('')}</select>`;
        case 'multiple_choice':
            return `<div class="choices">${options
                .map((o) => `<label class="check"><input type="checkbox" name="${name}[]" value="${esc(o.value)}"> <span>${esc(o.label)}</span></label>`)
                .join('')}</div>`;
        case 'number':
            return `<input id="${id}" type="number" name="${name}"${placeholder}${required}>`;
        case 'date':
            return `<input id="${id}" type="date" name="${name}"${required}>`;
        default:
            return `<input id="${id}" type="${field.attribute === EMAIL_ATTRIBUTE ? 'email' : 'text'}" name="${name}"${placeholder}${required}>`;
    }
}

function fieldBlock(field: FormField, index: number, labels: PreviewLabels): string {
    const help = field.help_text ? `<small>${esc(field.help_text)}</small>` : '';
    if (field.type === 'boolean') {
        return `<div class="field">${control(field, index, labels)}${help}</div>`;
    }
    const label = `<label for="f${index}">${esc(field.label)}${field.required ? ' <b class="req">*</b>' : ''}</label>`;

    return `<div class="field">${label}${control(field, index, labels)}${help}</div>`;
}

export function renderFormHtml(draft: FormDraft, labels: PreviewLabels): string {
    const d = draft.design;
    const primary = color(d.primary_color, '#0F6CBD');
    const background = color(d.background_color, '#F5F5F5');
    const text = color(d.text_color, '#242424');
    const buttonText = color(d.button_text_color, '#FFFFFF');
    const radius = Math.max(0, Math.min(40, Number.isFinite(d.border_radius) ? d.border_radius : 4));
    const logo = safeUrl(d.logo_url);
    const horizontal = d.layout === 'horizontal';

    const fields = draft.fields.map((f, i) => fieldBlock(f, i, labels)).join('');
    const gdpr = draft.gdpr.enabled
        ? `<label class="check gdpr"><input type="checkbox" name="gdpr_consent"${draft.gdpr.required ? ' required' : ''}> <span>${esc(draft.gdpr.text)}${draft.gdpr.required ? ' <b class="req">*</b>' : ''}</span></label>`
        : '';
    const captcha = draft.settings.captcha_enabled
        ? `<div class="captcha"><span class="box"></span>${esc(labels.captcha)}</div>`
        : '';

    return `<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<style>
*{box-sizing:border-box}
body{margin:0;padding:24px 16px;background:${background};color:${text};font-family:${font(d.font_family)};font-size:15px;line-height:1.45}
.card{max-width:${horizontal ? '760px' : '520px'};margin:0 auto;background:#fff;border-radius:${radius}px;padding:28px 24px;box-shadow:0 1px 3px rgba(0,0,0,.08)}
.logo{display:block;max-height:64px;max-width:200px;margin:0 auto 16px}
h1{font-size:22px;margin:0 0 8px;text-align:center}
.desc{margin:0 0 20px;text-align:center;white-space:pre-line}
.fields{display:flex;flex-direction:${horizontal ? 'row' : 'column'};flex-wrap:wrap;gap:14px;align-items:${horizontal ? 'flex-end' : 'stretch'}}
.field{display:flex;flex-direction:column;gap:4px;${horizontal ? 'flex:1 1 180px;' : ''}}
label{font-weight:600;font-size:14px}
input[type=text],input[type=email],input[type=number],input[type=date],select{width:100%;padding:9px 10px;border:1px solid #c8c8c8;border-radius:${radius}px;font:inherit;color:inherit;background:#fff}
small{color:#616161;font-size:12px}
.check{display:flex;gap:8px;align-items:flex-start;font-weight:400}
.check input{margin-top:3px}
.choices{display:flex;flex-direction:column;gap:4px}
.gdpr{margin-top:16px;font-size:13px}
.req{color:#c50f1f}
.captcha{display:inline-flex;align-items:center;gap:10px;margin-top:16px;padding:10px 14px;border:1px solid #d1d1d1;border-radius:4px;background:#fafafa;font-size:13px}
.captcha .box{width:20px;height:20px;border:2px solid #8a8886;border-radius:2px;background:#fff}
button{margin-top:18px;width:${horizontal ? 'auto' : '100%'};padding:11px 22px;border:0;border-radius:${radius}px;background:${primary};color:${buttonText};font:inherit;font-weight:600;cursor:pointer}
</style></head><body><div class="card">
${logo ? `<img class="logo" src="${esc(logo)}" alt="">` : ''}
${d.title ? `<h1>${esc(d.title)}</h1>` : ''}
${d.description ? `<p class="desc">${esc(d.description)}</p>` : ''}
<form><div class="fields">${fields}</div>${gdpr}${captcha}<button type="button">${esc(d.button_text)}</button></form>
</div></body></html>`;
}
