/**
 * WP-60 — every label the EmailBuilder shows.
 *
 * Every label comes from `fr.ts` (`editor`, `common`, `templates`,
 * `campaignSetup`, `conditionBuilder`); call sites read `text.*`.
 */
import { fr } from '@/Lib/i18n/fr';

const e = fr.editor;

export const text = {
    ...e,
    title: e.title,
    undo: e.undo,
    redo: e.redo,
    restorePrevious: e.restorePrevious,
    previewAndTest: e.previewAndTest,
    saveAndQuit: e.saveAndQuit,
    save: e.save,
    saving: e.saving,
    saved: e.saved,
    compileError: e.compileError,
    desktop: e.desktop,
    mobile: e.mobile,
    tabs: e.tabs,
    contentTabs: e.contentTabs,
    blocks: e.blocks,
    properties: e.properties,
    noSelection: e.noSelection,
    personalize: e.personalize,
    insertMergeTag: e.insertMergeTag,
    style: e.style,
    image: e.image,
    button: e.button,
    leaveConfirm: e.leaveConfirm,
    mergeTagGroups: fr.attributes.mergeTagGroups,
    restoreConfirm: fr.templates.restoreConfirm,
    preview: fr.campaignSetup.previewPanel,
    /** WP-61 — versions drawer (template history). */
    versions: {
        title: fr.templates.versions,
        versionNumber: fr.templates.versionNumber,
        current: fr.templates.currentVersion,
        restore: fr.templates.restoreVersion,
        confirm: fr.templates.restoreConfirm,
        restored: fr.templates.restored,
    },
    /** WP-61 — "Afficher si" summary of a dynamic-content rule. */
    condition: {
        and: fr.conditionBuilder.and,
        or: fr.conditionBuilder.or,
        empty: fr.conditionBuilder.empty,
        incomplete: fr.conditionBuilder.incomplete,
        booleanValues: fr.conditionBuilder.booleanValues,
    },
    common: {
        loading: fr.common.loading,
        back: fr.common.back,
        cancel: fr.common.cancel,
        close: fr.common.close,
        confirm: fr.common.confirm,
        retry: fr.common.retry,
        default: fr.common.default,
        genericError: fr.common.genericError,
        forbidden: fr.common.forbidden,
        notFound: fr.common.notFound,
        conflict: fr.common.conflict,
        unsavedChanges: fr.common.unsavedChanges,
        savedAt: fr.common.savedAt,
        add: fr.common.add,
        apply: fr.common.apply,
        edit: fr.common.edit,
        delete: fr.common.delete,
        rename: fr.common.rename,
        remove: fr.common.remove,
        name: fr.common.name,
        searchPlaceholder: fr.common.searchPlaceholder,
        noResults: fr.common.noResults,
        moreActions: fr.common.moreActions,
        deleteConfirmTitle: fr.common.deleteConfirmTitle,
        deleteConfirmBody: fr.common.deleteConfirmBody,
        createdBy: fr.common.createdBy,
        enabled: fr.common.enabled,
        refresh: fr.common.refresh,
    },
};

/** Replaces each `%d` / `%s` placeholder of a label, in order. */
export function format(template: string, ...values: (string | number)[]): string {
    let index = 0;

    return template.replace(/%[ds]/g, (match) => (index < values.length ? String(values[index++]) : match));
}
