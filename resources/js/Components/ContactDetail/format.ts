export function formatDateTime(value: string | null | undefined): string {
    return value ? new Date(value).toLocaleString('fr-FR') : '—';
}

export function formatDate(value: string | null | undefined): string {
    return value ? new Date(value).toLocaleDateString('fr-FR') : '—';
}

/** Replaces the first `%s` / `%d` placeholder of a fr.ts template. */
export function fill(template: string, ...values: Array<string | number>): string {
    let index = 0;

    return template.replace(/%[sd]/g, () => String(values[index++] ?? ''));
}
