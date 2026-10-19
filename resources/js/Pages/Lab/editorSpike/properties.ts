/**
 * WP-60S — Fluent "Propriétés" panel definitions: per grapesjs-mjml component
 * type, the MJML attributes edited with Fluent fields. grapesjs-mjml mirrors
 * attributes and styles, so `component.addAttributes()` is enough.
 */
export interface PropertyFieldDef {
    name: string;
    label: string;
    kind: 'text' | 'color' | 'select';
    placeholder?: string;
    options?: { value: string; label: string }[];
}

const align: PropertyFieldDef = {
    name: 'align',
    label: 'Alignement',
    kind: 'select',
    options: [
        { value: 'left', label: 'Gauche' },
        { value: 'center', label: 'Centre' },
        { value: 'right', label: 'Droite' },
    ],
};
const padding: PropertyFieldDef = { name: 'padding', label: 'Marge intérieure', kind: 'text', placeholder: '10px 25px' };
const color = (name: string, label: string): PropertyFieldDef => ({ name, label, kind: 'color', placeholder: '#242424' });
const text = (name: string, label: string, placeholder?: string): PropertyFieldDef => ({ name, label, kind: 'text', placeholder });

export const FIELDS_BY_TYPE: Record<string, PropertyFieldDef[]> = {
    'mj-body': [color('background-color', 'Couleur de fond'), text('width', 'Largeur du contenu', '600px')],
    'mj-section': [color('background-color', 'Couleur de fond'), text('background-url', 'Image de fond'), padding],
    'mj-column': [color('background-color', 'Couleur de fond'), text('width', 'Largeur', '50%'), padding],
    'mj-text': [color('color', 'Couleur du texte'), text('font-size', 'Taille', '14px'), text('line-height', 'Interligne', '1.5'), align, padding],
    'mj-button': [
        text('href', 'Lien', 'https://'),
        color('background-color', 'Couleur du bouton'),
        color('color', 'Couleur du texte'),
        text('border-radius', 'Arrondi', '4px'),
        align,
    ],
    'mj-image': [text('src', 'Source', 'https://'), text('alt', 'Texte alternatif'), text('href', 'Lien', 'https://'), text('width', 'Largeur', '600px'), align],
    'mj-divider': [color('border-color', 'Couleur'), text('border-width', 'Épaisseur', '1px'), padding],
    'mj-spacer': [text('height', 'Hauteur', '24px')],
    'mj-social': [text('icon-size', 'Taille des icônes', '24px'), align],
    'mj-social-element': [text('name', 'Réseau', 'facebook'), text('href', 'Lien', 'https://')],
    'mj-navbar-link': [text('href', 'Lien', 'https://'), color('color', 'Couleur')],
};
