<?php

namespace App\Modules\Templates\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ZipArchive;

/**
 * docs/45 §45.4.4 — `POST /templates/import-zip`. One .html + images; images
 * go to the public disk under `imports/{uuid}/` and `src` / `url()` are
 * rewritten. Nothing else about the template is persisted.
 */
class TemplateZipImportService
{
    private const IMAGE_EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
    private const MAX_ENTRIES = 300;
    private const MAX_UNCOMPRESSED_BYTES = 50 * 1024 * 1024;

    public function __construct(private readonly HtmlSanitizer $sanitizer) {}

    /**
     * @return array{html: string, title: string|null, assets: list<array{filename: string, url: string}>, warnings: list<string>}
     */
    public function import(UploadedFile $file): array
    {
        $zip = new ZipArchive();
        if ($zip->open($file->getRealPath()) !== true) {
            $this->fail('Archive ZIP invalide.');
        }

        try {
            if ($zip->numFiles > self::MAX_ENTRIES) {
                $this->fail("L'archive contient trop de fichiers.");
            }

            $htmlIndex = [];
            $images = [];
            $total = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $name = str_replace('\\', '/', (string) ($stat['name'] ?? ''));
                if ($stat === false || str_ends_with($name, '/') || str_starts_with(basename($name), '.') || str_contains($name, '__MACOSX/')) {
                    continue;
                }
                $total += (int) $stat['size'];
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                if (in_array($ext, ['html', 'htm'], true)) {
                    $htmlIndex[] = $i;
                } elseif (in_array($ext, self::IMAGE_EXTENSIONS, true)) {
                    $images[$i] = $name;
                }
            }
            if ($total > self::MAX_UNCOMPRESSED_BYTES) {
                $this->fail("L'archive décompressée est trop volumineuse.");
            }
            if (count($htmlIndex) !== 1) {
                $this->fail("L'archive doit contenir exactement un fichier HTML.");
            }

            $html = (string) $zip->getFromIndex($htmlIndex[0]);
            $htmlDir = dirname(str_replace('\\', '/', (string) $zip->getNameIndex($htmlIndex[0])));
            $folder = 'imports/'.Str::uuid()->toString();
            $disk = Storage::disk('public');

            $assets = [];
            $warnings = [];
            $used = [];
            foreach ($images as $index => $name) {
                $contents = $zip->getFromIndex($index);
                if ($contents === false) {
                    continue;
                }
                if (@getimagesizefromstring($contents) === false) {
                    $warnings[] = "Le fichier « {$name} » n'est pas une image valide et a été ignoré.";

                    continue;
                }
                $base = Str::slug(pathinfo($name, PATHINFO_FILENAME)) ?: 'image';
                $stored = $base.'.'.strtolower(pathinfo($name, PATHINFO_EXTENSION));
                for ($n = 2; isset($used[$stored]); $n++) {
                    $stored = $base.'-'.$n.'.'.strtolower(pathinfo($name, PATHINFO_EXTENSION));
                }
                $used[$stored] = true;
                $disk->put($folder.'/'.$stored, $contents);
                $url = $disk->url($folder.'/'.$stored);
                $assets[] = ['filename' => basename($name), 'url' => $url];

                $relative = $htmlDir === '.' ? $name : ltrim(substr($name, strlen($htmlDir)), '/');
                foreach (array_unique([$relative, './'.$relative, $name]) as $needle) {
                    $html = str_replace(
                        ['"'.$needle.'"', "'".$needle."'", '('.$needle.')'],
                        ['"'.$url.'"', "'".$url."'", '('.$url.')'],
                        $html,
                    );
                }
            }
        } finally {
            $zip->close();
        }

        $clean = $this->sanitizer->clean($html);

        return ['html' => $clean['html'], 'title' => $clean['title'], 'assets' => $assets, 'warnings' => array_merge($clean['warnings'], $warnings)];
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['file' => [$message]]);
    }
}
