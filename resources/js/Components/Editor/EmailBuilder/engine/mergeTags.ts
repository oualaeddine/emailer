/**
 * WP-60 — "Personnaliser" menu in the rich-text toolbar of text blocks
 * (doc 43 §43.4.4): inserts `{{ contact.NAME }}` merge tags from
 * `GET /contact-attributes/merge-tags`, resolved at send time by
 * MergeVariableResolver.
 */
import type { Editor } from 'grapesjs';
import type { MergeTag, MergeTagGroup } from '@/Lib/types/attributes';
import { text } from '../strings';

export const MERGE_TAG_ACTION = 'pj-merge-tag';

const GROUP_ORDER: MergeTagGroup[] = ['contact', 'links', 'campaign'];

function escapeHtml(value: string): string {
    return value.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

function buildSelect(tags: MergeTag[]): HTMLSelectElement {
    const select = document.createElement('select');
    select.className = 'gjs-field pj-merge-select';
    select.title = text.insertMergeTag;
    select.setAttribute('aria-label', text.insertMergeTag);

    const first = document.createElement('option');
    first.value = '';
    first.textContent = `${text.personalize}…`;
    select.appendChild(first);

    for (const group of GROUP_ORDER) {
        const rows = tags.filter((tag) => tag.group === group);
        if (rows.length === 0) {
            continue;
        }
        const optgroup = document.createElement('optgroup');
        optgroup.label = text.mergeTagGroups[group];
        for (const tag of rows) {
            const option = document.createElement('option');
            option.value = tag.tag;
            option.textContent = tag.label;
            optgroup.appendChild(option);
        }
        select.appendChild(optgroup);
    }

    return select;
}

export function registerMergeTagAction(editor: Editor, tags: MergeTag[]): void {
    if (tags.length === 0) {
        return;
    }
    const select = buildSelect(tags);
    // Keep the RTE selection while the menu is used.
    select.addEventListener('mousedown', (event) => event.stopPropagation());

    editor.RichTextEditor.add(MERGE_TAG_ACTION, {
        icon: select,
        event: 'change',
        result: (rte, action) => {
            const el = (action.btn?.querySelector('select') ?? select) as HTMLSelectElement;
            if (el.value) {
                rte.insertHTML(escapeHtml(el.value));
                // `insertHTML` re-adds the range that spans the inserted node, so the tag stays
                // selected and the next keystroke would replace it: collapse after the tag.
                rte.el.ownerDocument.getSelection()?.collapseToEnd();
                el.value = '';
            }
        },
        update: (_rte, action) => {
            const el = action.btn?.querySelector('select');
            if (el) {
                el.value = '';
            }

            return 0;
        },
    });
}
