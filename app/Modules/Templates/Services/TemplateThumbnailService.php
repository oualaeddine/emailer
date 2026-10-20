<?php

namespace App\Modules\Templates\Services;

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Templates\Models\Template;
use App\Modules\Templates\Sections\Models\SavedSection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * docs/45 §45.6.2 — 600x800 PNG thumbnails via Playwright/Chromium
 * (`scripts/thumbnail.mjs`). Every failure mode (node, playwright or Chromium
 * missing, timeout, unsupported model) is a graceful no-op: `null` is returned
 * and `thumbnail_path` is left untouched.
 */
class TemplateThumbnailService
{
    public const MODEL_TEMPLATE = 'template';

    public const MODEL_CAMPAIGN = 'campaign';

    public const MODEL_SAVED_SECTION = 'saved_section';

    /** Crop to the content height instead of the fixed 600x800 frame (saved sections). */
    private bool $fit = false;

    /** Whether the listener should queue thumbnails on save (off under tests unless enabled). */
    public static function enabled(): bool
    {
        return (bool) config('templates.thumbnails', ! app()->environment('testing'));
    }

    /**
     * @return string|null the stored path on the public disk, or null when nothing was rendered
     */
    public function renderFor(string $modelType, int $modelId): ?string
    {
        if ($modelType === self::MODEL_SAVED_SECTION) {
            return $this->renderSavedSection($modelId);
        }

        $model = match ($modelType) {
            self::MODEL_TEMPLATE => Template::query()->find($modelId),
            self::MODEL_CAMPAIGN => Campaign::query()->find($modelId),
            default => null,
        };
        if ($model === null) {
            return null;
        }

        $html = (string) ($model instanceof Template ? $model->html_content : $model->html_body);
        if (trim($html) === '') {
            return null;
        }

        $png = $this->renderHtml($html);
        if ($png === null) {
            return null;
        }

        $path = 'thumbnails/'.$modelType.'s/'.$model->uuid.'.png';
        Storage::disk('public')->put($path, $png);
        $model->forceFill(['thumbnail_path' => $path])->saveQuietly();

        return $path;
    }

    /**
     * A saved section is MJML: wrapped in a minimal document, compiled, then
     * rendered cropped to its own height (a section is much shorter than 800px).
     */
    private function renderSavedSection(int $id): ?string
    {
        $section = SavedSection::query()->find($id);
        if ($section === null || trim((string) $section->mjml) === '') {
            return null;
        }

        $compiled = app(MjmlCompiler::class)->compile('<mjml><mj-body>'.$section->mjml.'</mj-body></mjml>');
        if (trim($compiled->html) === '') {
            return null;
        }

        $this->fit = true;
        try {
            $png = $this->renderHtml($compiled->html);
        } finally {
            $this->fit = false;
        }
        if ($png === null) {
            return null;
        }

        $path = 'thumbnails/saved_sections/'.$section->uuid.'.png';
        Storage::disk('public')->put($path, $png);
        $section->forceFill(['thumbnail_path' => $path])->saveQuietly();

        return $path;
    }

    /**
     * @return string|null PNG bytes
     */
    public function renderHtml(string $html): ?string
    {
        $node = (string) config('templates.node_binary', 'node');
        $script = base_path('scripts/thumbnail.mjs');
        if (! is_file($script) || ! is_dir(base_path('node_modules/playwright'))) {
            Log::info('Thumbnail skipped: playwright is not installed.');

            return null;
        }

        $output = sys_get_temp_dir().'/thumb-'.bin2hex(random_bytes(6)).'.png';

        try {
            // The HTML is untrusted: it goes to the renderer on stdin ("-"), never as an argument.
            $command = [$node, $script, '-', $output];
            if ($this->fit) {
                $command[] = '2000';
                $command[] = '--fit';
            }
            if (! $this->execute($command, $html) || ! is_file($output)) {
                return null;
            }

            $png = file_get_contents($output);

            return $png === false || $png === '' ? null : $png;
        } finally {
            @unlink($output);
        }
    }

    /**
     * @param  list<string>  $command
     */
    protected function execute(array $command, string $stdin = ''): bool
    {
        try {
            $process = new Process($command, base_path());
            $process->setTimeout(60);
            $process->setInput($stdin);
            $process->run();

            if (! $process->isSuccessful()) {
                Log::info('Thumbnail skipped: renderer exited with '.$process->getExitCode().'.', ['stderr' => mb_substr($process->getErrorOutput(), 0, 300)]);

                return false;
            }

            return true;
        } catch (Throwable $e) {
            Log::info('Thumbnail skipped: '.$e->getMessage());

            return false;
        }
    }
}
