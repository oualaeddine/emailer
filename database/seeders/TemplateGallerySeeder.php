<?php

namespace Database\Seeders;

use App\Modules\Templates\Jobs\RenderThumbnailJob;
use App\Modules\Templates\Models\Template;
use App\Modules\Templates\Services\TemplateThumbnailService;
use Illuminate\Database\Seeder;

/**
 * docs/43 §43.4.5 / §43.9 — the built-in template gallery: 12 ORIGINAL designs
 * under `resources/gallery/<slug>/{design.mjml, design.html, meta.json}`.
 *
 * `design.mjml` is the editor source stored in `design_json.mjml`; `design.html`
 * is the hand-written responsive HTML stored in `html_content` (no MJML compiler
 * is needed at seed time). Idempotent: rows are upserted by (is_gallery, name),
 * where `name` is the stable gallery title from `meta.json`.
 */
class TemplateGallerySeeder extends Seeder
{
    public function run(): void
    {
        $root = resource_path('gallery');
        $directories = glob($root.'/*', GLOB_ONLYDIR) ?: [];
        sort($directories);

        foreach ($directories as $directory) {
            $meta = json_decode((string) file_get_contents($directory.'/meta.json'), true);
            $mjml = (string) file_get_contents($directory.'/design.mjml');
            $html = (string) file_get_contents($directory.'/design.html');

            $template = Template::query()->updateOrCreate(
                ['is_gallery' => true, 'name' => $meta['name']],
                [
                    'category' => null,
                    'gallery_category' => $meta['gallery_category'],
                    'html_content' => $html,
                    'editor_type' => 'dnd',
                    'scope' => $meta['scope'] ?? 'marketing',
                    'subject' => $meta['subject'] ?? null,
                    'is_archived' => false,
                    'design_json' => $this->design($mjml, $meta),
                ],
            );

            if ($template->thumbnail_path === null && TemplateThumbnailService::enabled()) {
                RenderThumbnailJob::dispatch(TemplateThumbnailService::MODEL_TEMPLATE, $template->id);
            }
        }
    }

    /**
     * `EmailDesign` (docs/45 §45.6.1). `project` is intentionally empty: the
     * editor rebuilds it from `mjml` on first open (docs/46 §2, rule 1).
     *
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function design(string $mjml, array $meta): array
    {
        $accent = $meta['accent_color'] ?? '#0F6CBD';

        return [
            'format' => 'grapesjs-mjml',
            'version' => 1,
            'mjml' => $mjml,
            'project' => ['assets' => [], 'styles' => [], 'symbols' => [], 'dataSources' => [], 'pages' => []],
            'global_styles' => [
                'font_family' => 'Arial, sans-serif',
                'text_color' => '#242424',
                'link_color' => $accent,
                'link_underline' => true,
                'background_color' => $meta['background_color'] ?? '#F5F5F5',
                'content_background_color' => '#FFFFFF',
                'content_width' => 600,
                'button' => ['background_color' => $accent, 'text_color' => '#FFFFFF', 'border_radius' => 4, 'font_weight' => '600'],
                'mobile' => ['font_scale' => 1, 'hide_on_mobile_padding' => false],
            ],
        ];
    }
}
