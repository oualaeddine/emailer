<?php

namespace App\Modules\Templates\Sections\Services;

/**
 * docs/45-brevo-api-contracts.md §45.7.1 `PremadeSection` — the static
 * pre-made row layouts of the EmailBuilder *Contenu › Sections* tab
 * (docs/43 §43.4.4). Each layout is one `<mj-section>` (self-closing tags
 * expanded, as the editor's HTML parser expects) with a schematic SVG
 * thumbnail generated from the same column description.
 */
class PremadeSectionCatalog
{
    private const PLACEHOLDER_IMAGE = 'https://placehold.co/600x300/EDEBE9/605E5C?text=Image';

    private const PLACEHOLDER_SQUARE = 'https://placehold.co/300x300/EDEBE9/605E5C?text=Image';

    private const PLACEHOLDER_LOGO = 'https://placehold.co/240x60/FFD400/111111?text=LOGO';

    /**
     * @return list<array{key: string, label: string, category: string, mjml: string, thumbnail_url: string}>
     */
    public function all(): array
    {
        return array_map(fn (array $row): array => [
            'key' => $row['key'],
            'label' => $row['label'],
            'category' => $row['category'],
            'mjml' => $row['mjml'],
            'thumbnail_url' => $this->thumbnail($row['sketch']),
        ], $this->definitions());
    }

    /**
     * @return list<array{key: string, label: string, category: string, mjml: string, sketch: list<list<string>>}>
     */
    private function definitions(): array
    {
        $text = '<mj-text>Présentez ici votre actualité, votre offre ou votre service en quelques lignes.</mj-text>';
        $title = fn (string $t, string $size = '22px', string $align = 'left'): string => '<mj-text font-size="'.$size.'" font-weight="700" line-height="1.25" align="'.$align.'">'.$t.'</mj-text>';
        $button = fn (string $label, string $align = 'left'): string => '<mj-button href="https://" align="'.$align.'">'.$label.'</mj-button>';
        $image = fn (string $src, string $alt = ''): string => '<mj-image src="'.$src.'" alt="'.$alt.'"></mj-image>';

        return [
            [
                'key' => 'header_logo_navigation',
                'label' => 'Logo et navigation',
                'category' => 'header',
                'sketch' => [['logo'], ['nav']],
                'mjml' => '<mj-section padding="12px 0px"><mj-column vertical-align="middle">'
                    .'<mj-image src="'.self::PLACEHOLDER_LOGO.'" width="120px" align="left" alt="Logo"></mj-image></mj-column>'
                    .'<mj-column vertical-align="middle"><mj-navbar align="right">'
                    .'<mj-navbar-link href="https://">Accueil</mj-navbar-link><mj-navbar-link href="https://">Offres</mj-navbar-link>'
                    .'<mj-navbar-link href="https://">Contact</mj-navbar-link></mj-navbar></mj-column></mj-section>',
            ],
            [
                'key' => 'header_logo_centered',
                'label' => 'Logo centré',
                'category' => 'header',
                'sketch' => [['logo-center']],
                'mjml' => '<mj-section padding="16px 0px"><mj-column>'
                    .'<mj-image src="'.self::PLACEHOLDER_LOGO.'" width="140px" align="center" alt="Logo"></mj-image>'
                    .'</mj-column></mj-section>',
            ],
            [
                'key' => 'hero_banner',
                'label' => 'Bannière',
                'category' => 'header',
                'sketch' => [['image', 'title', 'text', 'button-center']],
                'mjml' => '<mj-section padding="0px 0px 24px 0px"><mj-column>'
                    .$image(self::PLACEHOLDER_IMAGE, 'Bannière')
                    .$title('Votre titre accrocheur', '28px', 'center')
                    .'<mj-text align="center">Une phrase courte qui donne envie d’en savoir plus.</mj-text>'
                    .$button('Découvrir', 'center')
                    .'</mj-column></mj-section>',
            ],
            [
                'key' => 'two_columns_image_text',
                'label' => 'Image + texte',
                'category' => 'content',
                'sketch' => [['image'], ['title', 'text', 'button']],
                'mjml' => '<mj-section><mj-column vertical-align="middle">'.$image(self::PLACEHOLDER_SQUARE).'</mj-column>'
                    .'<mj-column vertical-align="middle">'.$title('Titre de la section').$text.$button('En savoir plus').'</mj-column></mj-section>',
            ],
            [
                'key' => 'two_columns_text_image',
                'label' => 'Texte + image',
                'category' => 'content',
                'sketch' => [['title', 'text', 'button'], ['image']],
                'mjml' => '<mj-section><mj-column vertical-align="middle">'.$title('Titre de la section').$text.$button('En savoir plus').'</mj-column>'
                    .'<mj-column vertical-align="middle">'.$image(self::PLACEHOLDER_SQUARE).'</mj-column></mj-section>',
            ],
            [
                'key' => 'three_columns_features',
                'label' => 'Trois atouts',
                'category' => 'content',
                'sketch' => [['image', 'title', 'text'], ['image', 'title', 'text'], ['image', 'title', 'text']],
                'mjml' => '<mj-section>'.implode('', array_map(
                    fn (string $t): string => '<mj-column>'.$image(self::PLACEHOLDER_SQUARE).$title($t, '18px', 'center')
                        .'<mj-text align="center">Décrivez cet atout en une ou deux phrases.</mj-text></mj-column>',
                    ['Qualité', 'Rapidité', 'Proximité'],
                )).'</mj-section>',
            ],
            [
                'key' => 'text_only',
                'label' => 'Titre et texte',
                'category' => 'content',
                'sketch' => [['title', 'text', 'text']],
                'mjml' => '<mj-section><mj-column>'.$title('Titre de la section').$text.'</mj-column></mj-section>',
            ],
            [
                'key' => 'call_to_action',
                'label' => 'Appel à l’action',
                'category' => 'content',
                'sketch' => [['title', 'button-center']],
                'mjml' => '<mj-section padding="32px 0px"><mj-column>'
                    .$title('Prêt à commencer ?', '24px', 'center')
                    .'<mj-text align="center">Profitez de notre offre dès aujourd’hui.</mj-text>'
                    .$button('J’en profite', 'center')
                    .'</mj-column></mj-section>',
            ],
            [
                'key' => 'product_card',
                'label' => 'Fiche produit',
                'category' => 'product',
                'sketch' => [['image'], ['title', 'text', 'button']],
                'mjml' => '<mj-section><mj-column vertical-align="middle">'.$image(self::PLACEHOLDER_SQUARE, 'Produit').'</mj-column>'
                    .'<mj-column vertical-align="middle">'.$title('Nom du produit', '20px')
                    .'<mj-text>Une courte description du produit.</mj-text>'
                    .'<mj-text font-size="20px" font-weight="700">4 900 DA</mj-text>'
                    .$button('Acheter').'</mj-column></mj-section>',
            ],
            [
                'key' => 'two_products',
                'label' => 'Deux produits',
                'category' => 'product',
                'sketch' => [['image', 'title', 'button-center'], ['image', 'title', 'button-center']],
                'mjml' => '<mj-section>'.implode('', array_map(
                    fn (string $name): string => '<mj-column>'.$image(self::PLACEHOLDER_SQUARE, $name).$title($name, '18px', 'center')
                        .'<mj-text align="center" font-weight="700">2 500 DA</mj-text>'.$button('Voir le produit', 'center').'</mj-column>',
                    ['Produit 1', 'Produit 2'],
                )).'</mj-section>',
            ],
            [
                'key' => 'footer_company',
                'label' => 'Pied de page société',
                'category' => 'footer',
                'sketch' => [['social', 'text-small', 'text-small']],
                'mjml' => '<mj-section padding="24px 0px"><mj-column>'
                    .'<mj-social icon-size="24px" mode="horizontal" align="center">'
                    .'<mj-social-element name="facebook" href="https://facebook.com"></mj-social-element>'
                    .'<mj-social-element name="instagram" href="https://instagram.com"></mj-social-element>'
                    .'<mj-social-element name="linkedin" href="https://linkedin.com"></mj-social-element></mj-social>'
                    .'<mj-text align="center" font-size="12px" color="#616161">Nom de votre société — Adresse, Ville</mj-text>'
                    .'<mj-text align="center" font-size="12px" color="#616161">Vous recevez cet e-mail car vous êtes inscrit à notre liste. '
                    .'<a href="{{ unsubscribe }}">Se désinscrire</a></mj-text>'
                    .'</mj-column></mj-section>',
            ],
            [
                'key' => 'footer_minimal',
                'label' => 'Pied de page simple',
                'category' => 'footer',
                'sketch' => [['text-small']],
                'mjml' => '<mj-section padding="16px 0px"><mj-column>'
                    .'<mj-text align="center" font-size="12px" color="#616161"><a href="{{ mirror }}">Voir la version en ligne</a> · '
                    .'<a href="{{ unsubscribe }}">Se désinscrire</a></mj-text>'
                    .'</mj-column></mj-section>',
            ],
        ];
    }

    /**
     * Schematic SVG (data URI) of the columns: grey image blocks, text lines,
     * brand buttons.
     *
     * @param  list<list<string>>  $columns
     */
    private function thumbnail(array $columns): string
    {
        $width = 240;
        $height = 120;
        $gap = 8;
        $count = max(1, count($columns));
        $colWidth = ($width - $gap * ($count + 1)) / $count;
        $shapes = [];

        foreach ($columns as $index => $items) {
            $x = $gap + $index * ($colWidth + $gap);
            $y = 10.0;
            foreach ($items as $item) {
                [$shape, $used] = $this->shape($item, $x, $y, $colWidth);
                $shapes[] = $shape;
                $y += $used + 6;
            }
        }

        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d"><rect width="%d" height="%d" rx="6" fill="#FFFFFF" stroke="#E0E0E0"/>%s</svg>',
            $width, $height, $width, $height, $width, $height, implode('', $shapes),
        );

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    /**
     * @return array{0: string, 1: float}
     */
    private function shape(string $item, float $x, float $y, float $w): array
    {
        $rect = fn (float $rx, float $ry, float $rw, float $rh, string $fill, int $r = 2): string => sprintf('<rect x="%.1f" y="%.1f" width="%.1f" height="%.1f" rx="%d" fill="%s"/>', $rx, $ry, $rw, $rh, $r, $fill);

        return match ($item) {
            'image' => [$rect($x, $y, $w, 40, '#D6D6D6'), 40],
            'logo' => [$rect($x, $y + 6, min(60, $w), 16, '#FFD400'), 28],
            'logo-center' => [$rect($x + ($w - 70) / 2, $y + 30, 70, 20, '#FFD400'), 80],
            'nav' => [implode('', array_map(fn (int $i): string => $rect($x + $w - ($i + 1) * 26, $y + 12, 20, 4, '#9E9E9E'), [0, 1, 2])), 28],
            'title' => [$rect($x, $y, $w * 0.7, 7, '#424242'), 7],
            'text' => [$rect($x, $y, $w, 4, '#BDBDBD').$rect($x, $y + 7, $w * 0.85, 4, '#BDBDBD'), 11],
            'text-small' => [$rect($x + $w * 0.2, $y, $w * 0.6, 3, '#BDBDBD'), 3],
            'button' => [$rect($x, $y, min(48, $w * 0.6), 12, '#0F6CBD', 3), 12],
            'button-center' => [$rect($x + ($w - min(56, $w * 0.6)) / 2, $y, min(56, $w * 0.6), 12, '#0F6CBD', 3), 12],
            'social' => [implode('', array_map(fn (int $i): string => sprintf('<circle cx="%.1f" cy="%.1f" r="6" fill="#9E9E9E"/>', $x + $w / 2 + ($i - 1) * 18, $y + 20), [0, 1, 2])), 32],
            default => ['', 0],
        };
    }
}
