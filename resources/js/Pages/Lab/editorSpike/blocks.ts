/**
 * WP-60S editor spike — doc 43 §43.4.4 block set mapped onto grapesjs-mjml
 * component types (docs/46-editor-spike.md §3).
 *
 * `support`:
 *  - native   → a grapesjs-mjml component type renders it as-is;
 *  - composed → built from native MJML components (no new component type);
 *  - custom   → needs a new GrapesJS component type and server support (WP-60/61/63).
 *
 * Labels are hardcoded in French: the Lab page is not a product page and
 * fr.ts is frozen for this package.
 */
import type { Editor } from 'grapesjs';
import type { EmailBlockType } from '@/Lib/types/editor';

export type BlockSupport = 'native' | 'composed' | 'custom';
export type BlockCategory = 'Blocs' | 'Sections' | 'Enregistrés';

export interface SpikeBlock {
    key: EmailBlockType | `saved_section:${string}`;
    label: string;
    category: BlockCategory;
    support: BlockSupport;
    /** grapesjs-mjml component type(s) involved. */
    mjmlTags: string[];
    /** MJML dropped on the canvas; null = not prototyped in the spike. */
    content: string | null;
}

const column = (inner: string) => `<mj-column>${inner}</mj-column>`;
const section = (...cols: string[]) => `<mj-section>${cols.join('')}</mj-section>`;
const placeholder = (text: string) =>
    `<mj-text align="center" color="#707070">${text}</mj-text>`;

export const SPIKE_BLOCKS: SpikeBlock[] = [
    {
        key: 'title',
        label: 'Titre',
        category: 'Blocs',
        support: 'composed',
        mjmlTags: ['mj-text'],
        content: '<mj-text font-size="28px" font-weight="700" line-height="1.2">Votre titre</mj-text>',
    },
    { key: 'text', label: 'Texte', category: 'Blocs', support: 'native', mjmlTags: ['mj-text'], content: '<mj-text>Saisissez votre texte ici.</mj-text>' },
    {
        key: 'image',
        label: 'Image',
        category: 'Blocs',
        support: 'native',
        mjmlTags: ['mj-image'],
        content: '<mj-image src="https://placehold.co/560x240?text=Image" alt="" />',
    },
    {
        key: 'button',
        label: 'Bouton',
        category: 'Blocs',
        support: 'native',
        mjmlTags: ['mj-button'],
        content: '<mj-button href="https://example.com">Bouton</mj-button>',
    },
    { key: 'divider', label: 'Séparateur', category: 'Blocs', support: 'native', mjmlTags: ['mj-divider'], content: '<mj-divider border-width="1px" border-color="#E0E0E0" />' },
    { key: 'spacer', label: 'Espacement', category: 'Blocs', support: 'native', mjmlTags: ['mj-spacer'], content: '<mj-spacer height="24px" />' },
    {
        key: 'social',
        label: 'Réseaux sociaux',
        category: 'Blocs',
        support: 'native',
        mjmlTags: ['mj-social', 'mj-social-element'],
        content:
            '<mj-social icon-size="24px" mode="horizontal"><mj-social-element name="facebook" href="https://facebook.com">Facebook</mj-social-element><mj-social-element name="instagram" href="https://instagram.com">Instagram</mj-social-element></mj-social>',
    },
    {
        key: 'html',
        label: 'HTML',
        category: 'Blocs',
        support: 'native',
        mjmlTags: ['mj-raw'],
        content: '<mj-raw><p style="margin:0;padding:8px 25px;font-family:Arial">Bloc HTML libre</p></mj-raw>',
    },
    {
        key: 'video',
        label: 'Vidéo',
        category: 'Blocs',
        support: 'composed',
        mjmlTags: ['mj-image'],
        content:
            '<mj-image src="https://placehold.co/560x315/111111/FFFFFF?text=%E2%96%B6+Vid%C3%A9o" href="https://www.youtube.com/" alt="Lire la vidéo" />',
    },
    {
        key: 'logo',
        label: 'Logo',
        category: 'Blocs',
        support: 'composed',
        mjmlTags: ['mj-image'],
        content: '<mj-image src="https://placehold.co/240x60/FFD400/111111?text=LOGO" width="120px" align="left" alt="Logo" />',
    },
    {
        key: 'navigation',
        label: 'Barre de navigation',
        category: 'Blocs',
        support: 'native',
        mjmlTags: ['mj-navbar', 'mj-navbar-link'],
        content:
            '<mj-navbar><mj-navbar-link href="https://example.com">Accueil</mj-navbar-link><mj-navbar-link href="https://example.com/offres">Offres</mj-navbar-link><mj-navbar-link href="https://example.com/contact">Contact</mj-navbar-link></mj-navbar>',
    },
    {
        key: 'columns',
        label: '1 colonne',
        category: 'Sections',
        support: 'native',
        mjmlTags: ['mj-section', 'mj-column'],
        content: section(column(placeholder('Déposez un bloc ici'))),
    },
    {
        key: 'columns',
        label: '2 colonnes',
        category: 'Sections',
        support: 'native',
        mjmlTags: ['mj-section', 'mj-column'],
        content: section(column(placeholder('Colonne 1')), column(placeholder('Colonne 2'))),
    },
    {
        key: 'columns',
        label: '3 colonnes',
        category: 'Sections',
        support: 'native',
        mjmlTags: ['mj-section', 'mj-column'],
        content: section(column(placeholder('1')), column(placeholder('2')), column(placeholder('3'))),
    },
    {
        key: 'columns',
        label: '4 colonnes',
        category: 'Sections',
        support: 'composed',
        mjmlTags: ['mj-section', 'mj-column'],
        content: section(column(placeholder('1')), column(placeholder('2')), column(placeholder('3')), column(placeholder('4'))),
    },
    {
        key: 'product',
        label: 'Fiche produit',
        category: 'Sections',
        support: 'composed',
        mjmlTags: ['mj-section', 'mj-column', 'mj-image', 'mj-text', 'mj-button'],
        content: section(
            column('<mj-image src="https://placehold.co/260x260?text=Produit" alt="Produit" />'),
            column(
                '<mj-text font-size="18px" font-weight="700">Nom du produit</mj-text><mj-text>Courte description du produit.</mj-text><mj-text font-size="20px" font-weight="700">2 500 DA</mj-text><mj-button href="https://example.com/produit">Acheter</mj-button>',
            ),
        ),
    },
    {
        key: 'dynamic_content',
        label: 'Contenu dynamique',
        category: 'Blocs',
        support: 'custom',
        mjmlTags: ['mj-wrapper (type GrapesJS dédié)'],
        content: null,
    },
];

let savedCounter = 0;

function blockId(block: SpikeBlock, index: number): string {
    return `pj-${block.key}-${index}`;
}

/** Registers the spike blocks; returns block id → spike block. */
export function registerSpikeBlocks(editor: Editor): Map<string, SpikeBlock> {
    const registry = new Map<string, SpikeBlock>();

    SPIKE_BLOCKS.forEach((block, index) => {
        if (block.content === null) {
            return;
        }
        const id = blockId(block, index);
        editor.Blocks.add(id, { label: block.label, category: block.category, content: block.content });
        registry.set(id, block);
    });

    return registry;
}

/**
 * "Enregistrés": only whole sections can be saved (doc 43 §43.4.4). The
 * component JSON is what WP-61 stores as `SavedSection.component`, the MJML
 * as `SavedSection.mjml`.
 */
export function addSavedSectionBlock(
    editor: Editor,
    registry: Map<string, SpikeBlock>,
    name: string,
    mjml: string,
): string {
    savedCounter += 1;
    const id = `pj-saved-${savedCounter}`;
    const block: SpikeBlock = {
        key: `saved_section:${id}`,
        label: name,
        category: 'Enregistrés',
        support: 'composed',
        mjmlTags: ['mj-section'],
        content: mjml,
    };
    editor.Blocks.add(id, { label: name, category: 'Enregistrés', content: mjml });
    registry.set(id, block);

    return id;
}
